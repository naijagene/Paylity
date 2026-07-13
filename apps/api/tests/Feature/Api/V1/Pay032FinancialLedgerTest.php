<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use App\Models\TransactionFinancial;
use App\Services\Finance\FinancialCloseService;
use App\Services\Finance\LedgerBackfillService;
use App\Services\Finance\LedgerPostingService;
use App\Services\Finance\SettlementReconciliationService;
use App\Support\Finance\Money;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Pay032FinancialLedgerTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.operator.access_key' => self::OPERATOR_KEY]);
        $this->seed(LedgerAccountSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_payment_posting_is_balanced_and_idempotent(): void
    {
        $transaction = $this->createPaidTransaction();

        $posting = app(LedgerPostingService::class)->postPaymentReceived($transaction);
        $this->assertNotNull($posting);

        $debits = (int) LedgerEntry::query()->where('entry_type', 'debit')->sum('amount_kobo');
        $credits = (int) LedgerEntry::query()->where('entry_type', 'credit')->sum('amount_kobo');
        $this->assertSame($debits, $credits);

        $duplicate = app(LedgerPostingService::class)->postPaymentReceived($transaction->fresh());
        $this->assertNull($duplicate);
        $this->assertSame(1, LedgerTransaction::query()->where('event_type', LedgerEventType::PAYMENT_RECEIVED)->count());
    }

    public function test_failed_payment_produces_no_revenue_posting(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260713-FAIL01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_FAILED,
            'verified_phone' => false,
        ]);

        $posting = app(LedgerPostingService::class)->postPaymentReceived($transaction);
        $this->assertNull($posting);
        $this->assertSame(0, LedgerTransaction::query()->count());
    }

    public function test_fulfilled_transaction_creates_cost_and_revenue_postings(): void
    {
        $transaction = $this->createFulfilledTransaction();

        app(LedgerPostingService::class)->postPaymentReceived($transaction);
        app(LedgerPostingService::class)->postFulfillmentRecognized($transaction->fresh());

        $this->assertTrue(
            LedgerTransaction::query()->where('event_type', LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED)->exists(),
        );

        $financial = TransactionFinancial::query()->where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($financial);
        $this->assertSame(Money::nairaToKobo(1000), $financial->provider_cost_kobo);
        $this->assertSame('provisional', $financial->provider_cost_status);
    }

    public function test_duplicate_fulfillment_posting_is_idempotent(): void
    {
        $transaction = $this->createFulfilledTransaction();

        $service = app(LedgerPostingService::class);
        $service->postPaymentReceived($transaction);
        $service->postFulfillmentRecognized($transaction->fresh());
        $service->postFulfillmentRecognized($transaction->fresh());

        $this->assertSame(
            1,
            LedgerTransaction::query()->where('event_type', LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED)->count(),
        );
    }

    public function test_backfill_is_idempotent_for_historical_transactions(): void
    {
        $transaction = $this->createFulfilledTransaction();

        $service = app(LedgerBackfillService::class);
        $first = $service->backfill(reference: $transaction->reference, dryRun: false, repair: true);
        $second = $service->backfill(reference: $transaction->reference, dryRun: false, repair: true);

        $this->assertSame(1, $first['payment_postings_created']);
        $this->assertSame(1, $first['fulfillment_postings_created']);
        $this->assertSame(1, $second['already_posted']);
    }

    public function test_settlement_dry_run_makes_no_changes(): void
    {
        $this->createPaidTransaction();

        $summary = app(SettlementReconciliationService::class)->reconcile(dryRun: true, repair: true);

        $this->assertGreaterThan(0, $summary['inspected']);
        $this->assertSame(0, LedgerTransaction::query()->where('event_type', LedgerEventType::SETTLEMENT_RECEIVED)->count());
    }

    public function test_daily_close_detects_metrics_and_finalizes_snapshot(): void
    {
        $transaction = $this->createFulfilledTransaction();
        app(LedgerPostingService::class)->postPaymentReceived($transaction);
        app(LedgerPostingService::class)->postFulfillmentRecognized($transaction->fresh());

        $date = now()->toDateString();
        $result = app(FinancialCloseService::class)->close(date: $date, dryRun: false, repair: true);

        $this->assertContains($result['status'], ['finalized', 'finalized_with_exceptions']);
        $this->assertGreaterThan(0, $result['metrics']['gross_collections_kobo']);
    }

    public function test_finance_endpoints_require_operator_auth(): void
    {
        $this->getJson('/api/v1/ops/finance')
            ->assertUnauthorized()
            ->assertJsonPath('errors.code', 'OPERATOR_ACCESS_DENIED');
    }

    public function test_finance_dashboard_matches_ledger_activity(): void
    {
        $transaction = $this->createFulfilledTransaction();
        app(LedgerPostingService::class)->postPaymentReceived($transaction);
        app(LedgerPostingService::class)->postFulfillmentRecognized($transaction->fresh());

        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/finance');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'cards' => [
                        'gross_collection_today_kobo',
                        'paystack_clearing_kobo',
                    ],
                    'recent_postings',
                ],
            ]);

        $this->assertNotEmpty($response->json('data.recent_postings'));
    }

    public function test_transaction_detail_includes_finance_section(): void
    {
        $transaction = $this->createFulfilledTransaction();
        app(LedgerPostingService::class)->postPaymentReceived($transaction);
        app(LedgerPostingService::class)->postFulfillmentRecognized($transaction->fresh());

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/transactions/'.$transaction->reference)
            ->assertOk()
            ->assertJsonPath('data.finance.summary.customer_paid_kobo', Money::nairaToKobo(1100))
            ->assertJsonPath('data.finance.summary.provider_cost_status', 'provisional');
    }

    private function createPaidTransaction(): Transaction
    {
        return Transaction::query()->create([
            'reference' => 'PYL-20260713-PAY'.uniqid(),
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'payment_reference' => 'PSK-TEST-001',
            'verified_phone' => false,
        ]);
    }

    private function createFulfilledTransaction(): Transaction
    {
        $transaction = $this->createPaidTransaction();
        $transaction->update([
            'status' => TransactionStatus::FULFILLED,
            'fulfilled_at' => now(),
            'fulfillment_provider' => 'vtpass',
            'fulfillment_reference' => 'VTP-TEST-001',
        ]);

        return $transaction->fresh();
    }
}
