<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\WebhookEvent;
use App\Services\Fulfillment\FulfillmentRetryService;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\WebhookEventService;
use App\Support\Platform\SystemSettingKeys;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class PaystackReliabilityTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    private const SECRET = 'sk_test_secret';

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSettingsSeeder::class);
        $this->seedProductCatalog();

        $this->withIntegratedFeatureFlags([
            'FEATURE_PAYSTACK' => true,
            'FEATURE_VTPASS' => false,
        ]);

        config([
            'services.paystack.enabled' => true,
            'services.paystack.secret_key' => self::SECRET,
            'services.paystack.base_url' => 'https://api.paystack.co',
            'services.operator.access_key' => self::OPERATOR_KEY,
        ]);
    }

    public function test_duplicate_webhook_is_ignored_safely(): void
    {
        $transaction = $this->createPendingTransaction('PYL-20260708-DUP01');
        $this->fakePaystackVerify($transaction->reference);

        $payload = json_encode([
            'id' => 9001,
            'event' => 'charge.success',
            'data' => ['reference' => $transaction->reference],
        ]);
        $signature = hash_hmac('sha512', $payload, self::SECRET);
        $headers = [
            'HTTP_X-Paystack-Signature' => $signature,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        $this->call('POST', '/api/v1/payments/paystack/webhook', [], [], [], $headers, $payload)
            ->assertOk();

        $response = $this->call('POST', '/api/v1/payments/paystack/webhook', [], [], [], $headers, $payload);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'duplicate');

        $this->assertSame(1, WebhookEvent::query()->where('event_id', '9001')->count());
        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_SUCCESS,
        ]);
    }

    public function test_webhook_verify_failure_marks_event_failed_for_reconciliation(): void
    {
        $transaction = $this->createPendingTransaction('PYL-20260708-FAIL01');

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 99999,
                    'reference' => $transaction->reference,
                    'currency' => 'NGN',
                ],
            ]),
        ]);

        $payload = json_encode([
            'id' => 9002,
            'event' => 'charge.success',
            'data' => ['reference' => $transaction->reference],
        ]);
        $signature = hash_hmac('sha512', $payload, self::SECRET);

        $this->call(
            'POST',
            '/api/v1/payments/paystack/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Paystack-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload,
        )->assertOk();

        $this->assertDatabaseHas('webhook_events', [
            'event_id' => '9002',
            'status' => WebhookEventService::STATUS_FAILED,
        ]);
    }

    public function test_browser_abandoned_flow_is_recovered_by_reconciliation(): void
    {
        $this->travel(-20)->minutes();

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260708-ABANDON',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_PENDING,
            'payment_provider' => 'paystack',
            'verified_phone' => false,
        ]);

        $this->travelBack();
        $this->fakePaystackVerify($transaction->reference);

        $summary = app(PaymentReconciliationService::class)->reconcile(15, 30);

        $this->assertGreaterThanOrEqual(1, $summary['payments_checked']);

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_SUCCESS,
        ]);
    }

    public function test_callback_before_webhook_does_not_confirm_until_authoritative_verify(): void
    {
        $transaction = $this->createPendingTransaction('PYL-20260708-ORDER1');

        $this->getJson('/api/v1/payments/paystack/callback?reference='.$transaction->reference)
            ->assertOk()
            ->assertJsonPath('message', 'Paystack callback received. Payment is not confirmed from callback alone.');

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_PENDING,
        ]);

        $this->fakePaystackVerify($transaction->reference);
        $payload = json_encode([
            'id' => 9003,
            'event' => 'charge.success',
            'data' => ['reference' => $transaction->reference],
        ]);
        $signature = hash_hmac('sha512', $payload, self::SECRET);

        $this->call(
            'POST',
            '/api/v1/payments/paystack/webhook',
            [],
            [],
            [],
            [
                'HTTP_X-Paystack-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            $payload,
        )->assertOk();

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'status' => TransactionStatus::PAYMENT_SUCCESS,
        ]);
    }

    public function test_retry_exhaustion_escalates_to_manual_review(): void
    {
        app(\App\Services\Platform\SystemSettingsService::class)->set(
            SystemSettingKeys::FULFILLMENT_RETRY_MAX_ATTEMPTS,
            1,
        );

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260708-RETRY1',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FAILED,
            'payment_provider' => 'paystack',
            'failure_reason' => 'Provider unavailable',
            'response_payload' => ['auto_fulfill' => ['attempted' => true, 'outcome' => 'failed']],
            'verified_phone' => false,
            'fulfillment_retry_count' => 0,
            'next_fulfillment_retry_at' => now()->subMinute(),
        ]);

        $retryService = app(FulfillmentRetryService::class);
        $retryService->processDueRetries();

        $this->assertDatabaseHas('transactions', [
            'reference' => $transaction->reference,
            'needs_manual_review' => true,
        ]);

        $this->assertDatabaseHas('fulfillment_attempts', [
            'transaction_id' => $transaction->id,
            'outcome' => 'dead_letter',
        ]);
    }

    public function test_ops_reliability_endpoint_exposes_queues_and_webhook_metrics(): void
    {
        WebhookEvent::query()->create([
            'provider' => 'paystack',
            'event_id' => 'evt-failed-1',
            'event_type' => 'charge.success',
            'reference' => 'PYL-20260708-OPS01',
            'payload_hash' => hash('sha256', 'payload'),
            'status' => WebhookEventService::STATUS_FAILED,
            'payload' => ['event' => 'charge.success'],
        ]);

        Transaction::query()->create([
            'reference' => 'PYL-20260708-OPS01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FAILED,
            'needs_manual_review' => true,
            'manual_review_reason' => 'Retry exhausted',
            'manual_review_at' => now(),
            'verified_phone' => false,
        ]);

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/reliability');

        $response
            ->assertOk()
            ->assertJsonPath('data.webhooks.failed_24h', 1)
            ->assertJsonPath('data.manual_review.count', 1)
            ->assertJsonStructure([
                'data' => [
                    'webhooks',
                    'reconciliation',
                    'retry_queue',
                    'manual_review',
                    'retry_items',
                    'config',
                ],
            ]);
    }

    public function test_reconcile_payments_command_runs_successfully(): void
    {
        $this->assertSame(0, Artisan::call('paylity:reconcile-payments'));
    }

    private function createPendingTransaction(string $reference = 'PYL-20260708-VERIFY1'): Transaction
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
            'status' => TransactionStatus::PAYMENT_PENDING,
            'payment_provider' => 'paystack',
            'verified_phone' => false,
        ]);
    }

    private function fakePaystackVerify(string $reference): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'amount' => 110000,
                    'reference' => $reference,
                    'gateway_response' => 'Successful',
                    'paid_at' => '2026-07-08T12:00:00.000000Z',
                    'currency' => 'NGN',
                ],
            ]),
        ]);
    }
}
