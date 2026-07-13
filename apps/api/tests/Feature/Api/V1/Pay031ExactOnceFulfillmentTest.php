<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FulfillmentAttemptStatus;
use App\Enums\TransactionStatus;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;
use App\Services\Fulfillment\ExactOnceFulfillmentService;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class Pay031ExactOnceFulfillmentTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSettingsSeeder::class);
        $this->seedProductCatalog();

        $this->withIntegratedFeatureFlags([
            'FEATURE_PAYSTACK' => true,
            'FEATURE_VTPASS' => true,
            'FEATURE_VTPASS_AUTO_FULFILL' => false,
        ]);

        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.auto_fulfill' => false,
            'services.vtpass.base_url' => 'https://sandbox.vtpass.com',
            'services.vtpass.username' => 'vtpass-user',
            'services.vtpass.password' => 'vtpass-pass',
            'services.vtpass.api_key' => 'vtpass-api-key',
            'services.operator.access_key' => self::OPERATOR_KEY,
        ]);
    }

    public function test_concurrent_fulfillment_triggers_create_single_provider_request(): void
    {
        $transaction = $this->createPaidTransaction('PYL-20260710-CONC01');
        $payCount = 0;

        Http::fake(function () use (&$payCount) {
            $payCount++;

            return Http::response([
                'code' => '000',
                'response_description' => 'TRANSACTION SUCCESSFUL',
                'requestId' => 'vtpass-1',
            ]);
        });

        $service = app(ExactOnceFulfillmentService::class);

        $first = $service->requestFromOperator($transaction);
        $second = $service->requestFromOperator($transaction->fresh());

        $this->assertTrue($first->fulfilled() || $first->outcome === 'failed');
        $this->assertTrue($second->ignored() || $second->outcome === 'already_fulfilled');

        $this->assertSame(1, $payCount);
        $this->assertSame(1, FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->where('status', FulfillmentAttemptStatus::SUCCEEDED)
            ->count());
    }

    public function test_fulfilled_transaction_returns_idempotent_result(): void
    {
        $transaction = $this->createPaidTransaction('PYL-20260710-IDEM01');

        Http::fake([
            'https://sandbox.vtpass.com/api/pay' => Http::response([
                'code' => '000',
                'response_description' => 'TRANSACTION SUCCESSFUL',
                'requestId' => 'vtpass-idem',
            ]),
        ]);

        $service = app(ExactOnceFulfillmentService::class);
        $first = $service->requestFromOperator($transaction);
        $second = $service->requestFromOperator($transaction->fresh());

        $this->assertTrue($first->fulfilled());
        $this->assertSame('already_fulfilled', $second->outcome);
        Http::assertSentCount(1);
    }

    public function test_uncertain_attempt_blocks_second_purchase_request(): void
    {
        $transaction = $this->createPaidTransaction('PYL-20260710-UNC01');

        Http::fake([
            'https://sandbox.vtpass.com/api/pay' => Http::response([
                'code' => '099',
                'response_description' => 'TRANSACTION PROCESSING',
            ]),
        ]);

        $service = app(ExactOnceFulfillmentService::class);
        $first = $service->requestFromOperator($transaction);
        $this->assertSame('uncertain', $first->outcome);

        $second = $service->requestFromAutomaticRetry($transaction->fresh());
        $this->assertSame('active_attempt', $second->outcome);
        Http::assertSentCount(1);
    }

    public function test_payment_reconciliation_dry_run_makes_no_changes(): void
    {
        $transaction = $this->createPaidTransaction('PYL-20260710-DRY01');
        $transaction->update(['updated_at' => now()->subHour()]);

        Artisan::call('paylity:reconcile-payments', [
            '--reference' => $transaction->reference,
            '--dry-run' => true,
        ]);

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_SUCCESS,
        ]);
        $this->assertSame(0, FulfillmentAttempt::query()->count());
    }

    public function test_ops_reconciliation_endpoint_requires_operator_auth(): void
    {
        $this->getJson('/api/v1/ops/reconciliation')->assertStatus(401);
    }

    public function test_ops_reconciliation_snapshot_returns_summary(): void
    {
        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/reconciliation')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'paid_unfulfilled',
                        'stale_payment_pending',
                        'manual_review',
                    ],
                    'queues',
                    'config',
                ],
            ]);
    }

    public function test_manual_review_blocks_automatic_retry(): void
    {
        $transaction = $this->createPaidTransaction('PYL-20260710-MR01');
        $transaction->update([
            'status' => TransactionStatus::FAILED,
            'needs_manual_review' => true,
            'manual_review_reason' => 'Investigate',
            'next_fulfillment_retry_at' => now()->subMinute(),
        ]);

        Http::fake();

        $service = app(ExactOnceFulfillmentService::class);
        $result = $service->requestFromAutomaticRetry($transaction);

        $this->assertSame('manual_review', $result->outcome);
        Http::assertNothingSent();
    }

    public function test_fulfilled_transaction_has_exactly_one_successful_attempt(): void
    {
        $transaction = $this->createPaidTransaction('PYL-20260710-ONE01');

        Http::fake([
            'https://sandbox.vtpass.com/api/pay' => Http::response([
                'code' => '000',
                'response_description' => 'TRANSACTION SUCCESSFUL',
                'requestId' => 'vtpass-one',
            ]),
        ]);

        app(ExactOnceFulfillmentService::class)->requestFromOperator($transaction);

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::FULFILLED,
        ]);

        $this->assertSame(1, FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->where('status', FulfillmentAttemptStatus::SUCCEEDED)
            ->count());
    }

    public function test_provider_reconciliation_repairs_success_after_local_timeout(): void
    {
        $transaction = $this->createPaidTransaction('PYL-20260710-REP01');
        $requestId = $transaction->reference.'-F01';

        FulfillmentAttempt::query()->create([
            'transaction_id' => $transaction->id,
            'attempt_number' => 1,
            'trigger_source' => 'operator',
            'provider' => 'vtpass',
            'request_id' => $requestId,
            'status' => FulfillmentAttemptStatus::UNCERTAIN,
            'outcome' => 'uncertain',
            'actor' => 'operator',
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subHour(),
            'attempted_at' => now()->subHour(),
        ]);

        $transaction->update(['status' => TransactionStatus::FULFILLMENT_PENDING]);

        Http::fake([
            'https://sandbox.vtpass.com/api/requery' => Http::response([
                'code' => '000',
                'response_description' => 'TRANSACTION SUCCESSFUL',
                'content' => [
                    'transactions' => [
                        'transactionId' => 'vtpass-repaired',
                        'status' => 'delivered',
                    ],
                ],
            ]),
        ]);

        Artisan::call('paylity:reconcile-fulfillments', [
            '--reference' => $transaction->reference,
        ]);

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::FULFILLED,
            'fulfillment_reference' => 'vtpass-repaired',
        ]);
    }

    private function createPaidTransaction(string $reference): Transaction
    {
        return Transaction::query()->create([
            'reference' => $reference,
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'request_payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
            'verified_phone' => false,
        ]);
    }
}
