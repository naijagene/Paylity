<?php

namespace Tests\Feature\Console;

use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use App\Services\Finance\LedgerBackfillService;
use App\Services\Finance\LedgerPostingService;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Pay032bLedgerBackfillLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_fulfilled_historical_transaction_produces_payment_and_fulfillment_in_dry_run(): void
    {
        $transaction = $this->createHistoricalTransaction([
            'status' => TransactionStatus::FULFILLED,
            'payment_reference' => 'PSK-HIST-FUL-001',
            'fulfilled_at' => now()->subDays(3),
            'fulfillment_provider' => 'vtpass',
            'fulfillment_reference' => 'VTP-HIST-001',
        ]);

        $summary = app(LedgerBackfillService::class)->backfill(
            reference: $transaction->reference,
            dryRun: true,
            repair: true,
            verbose: true,
        );

        $this->assertSame(1, $summary['transactions_inspected']);
        $this->assertSame(1, $summary['payment_postings_created']);
        $this->assertSame(1, $summary['fulfillment_postings_created']);
        $this->assertSame(0, LedgerTransaction::query()->count());
    }

    public function test_payment_success_unfulfilled_posts_payment_only(): void
    {
        $transaction = $this->createHistoricalTransaction([
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'payment_reference' => 'PSK-HIST-PAY-001',
        ]);

        $summary = app(LedgerBackfillService::class)->backfill(
            reference: $transaction->reference,
            dryRun: true,
        );

        $this->assertSame(1, $summary['payment_postings_created']);
        $this->assertSame(0, $summary['fulfillment_postings_created']);
    }

    public function test_failed_fulfillment_with_confirmed_payment_posts_payment_only(): void
    {
        $transaction = $this->createHistoricalTransaction([
            'status' => TransactionStatus::FAILED,
            'payment_reference' => 'PSK-HIST-FAIL-001',
            'failure_reason' => 'Provider timeout',
        ]);

        $summary = app(LedgerBackfillService::class)->backfill(
            reference: $transaction->reference,
            dryRun: true,
        );

        $this->assertSame(1, $summary['payment_postings_created']);
        $this->assertSame(0, $summary['fulfillment_postings_created']);
    }

    public function test_payment_failed_transaction_is_skipped(): void
    {
        $transaction = $this->createHistoricalTransaction([
            'status' => TransactionStatus::PAYMENT_FAILED,
        ]);

        $summary = app(LedgerBackfillService::class)->backfill(
            reference: $transaction->reference,
            dryRun: true,
            verbose: true,
        );

        $this->assertSame(1, $summary['transactions_inspected']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(0, $summary['payment_postings_created']);
        $this->assertSame('terminal_unpaid_status', $summary['verbose_details'][0]['reason']);
    }

    public function test_manual_review_posts_confirmed_payment_only(): void
    {
        $transaction = $this->createHistoricalTransaction([
            'status' => TransactionStatus::FULFILLED,
            'payment_reference' => 'PSK-HIST-REVIEW-001',
            'needs_manual_review' => true,
            'manual_review_reason' => 'Provider uncertainty',
            'fulfilled_at' => now()->subDay(),
        ]);

        $summary = app(LedgerBackfillService::class)->backfill(
            reference: $transaction->reference,
            dryRun: true,
        );

        $this->assertSame(1, $summary['payment_postings_created']);
        $this->assertSame(0, $summary['fulfillment_postings_created']);
    }

    public function test_historical_transaction_without_default_today_filter_is_selected(): void
    {
        $old = $this->createHistoricalTransaction([
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'payment_reference' => 'PSK-HIST-OLD-001',
            'created_at' => now()->subMonths(2),
        ]);

        $summary = app(LedgerBackfillService::class)->backfill(
            limit: 10,
            dryRun: true,
        );

        $this->assertGreaterThanOrEqual(1, $summary['eligible_in_database']);
        $this->assertGreaterThanOrEqual(1, $summary['candidates_selected']);
        $this->assertGreaterThanOrEqual(1, $summary['payment_postings_created']);
    }

    public function test_confirmed_payment_via_verify_payload_without_payment_reference(): void
    {
        $transaction = $this->createHistoricalTransaction([
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'payment_reference' => null,
            'response_payload' => ['verify' => ['status' => 'success']],
        ]);

        $summary = app(LedgerBackfillService::class)->backfill(
            reference: $transaction->reference,
            dryRun: true,
        );

        $this->assertSame(1, $summary['payment_postings_created']);
    }

    public function test_repeated_backfill_repair_remains_idempotent(): void
    {
        $transaction = $this->createHistoricalTransaction([
            'status' => TransactionStatus::FULFILLED,
            'payment_reference' => 'PSK-HIST-IDEM-001',
            'fulfilled_at' => now()->subDays(2),
        ]);

        $service = app(LedgerBackfillService::class);

        $first = $service->backfill(reference: $transaction->reference, dryRun: false, repair: true);
        $second = $service->backfill(reference: $transaction->reference, dryRun: false, repair: true);

        $this->assertSame(1, $first['payment_postings_created']);
        $this->assertSame(1, $first['fulfillment_postings_created']);
        $this->assertSame(1, $second['already_posted']);
        $this->assertSame(1, LedgerTransaction::query()->where('event_type', LedgerEventType::PAYMENT_RECEIVED)->count());
        $this->assertSame(1, LedgerTransaction::query()->where('event_type', LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED)->count());
    }

    public function test_verbose_mode_reports_skip_reason_per_reference(): void
    {
        $transaction = $this->createHistoricalTransaction([
            'status' => TransactionStatus::CREATED,
        ]);

        $summary = app(LedgerBackfillService::class)->backfill(
            reference: $transaction->reference,
            dryRun: true,
            verbose: true,
        );

        $this->assertNotEmpty($summary['verbose_details']);
        $this->assertSame($transaction->reference, $summary['verbose_details'][0]['reference']);
        $this->assertSame('terminal_unpaid_status', $summary['verbose_details'][0]['reason']);
    }

    public function test_batch_prefers_eligible_transactions_over_created_noise(): void
    {
        for ($index = 0; $index < 5; $index++) {
            $this->createHistoricalTransaction([
                'reference' => 'PYL-20260101-NOISE'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'status' => TransactionStatus::CREATED,
            ]);
        }

        $eligible = $this->createHistoricalTransaction([
            'reference' => 'PYL-20260101-ELIGIBLE01',
            'status' => TransactionStatus::FULFILLED,
            'payment_reference' => 'PSK-ELIGIBLE-001',
            'fulfilled_at' => now()->subWeek(),
        ]);

        $summary = app(LedgerBackfillService::class)->backfill(limit: 10, dryRun: true);

        $this->assertSame(1, $summary['candidates_selected']);
        $this->assertSame(1, $summary['transactions_inspected']);
        $this->assertSame(1, $summary['payment_postings_created']);
        $this->assertSame(1, $summary['fulfillment_postings_created']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createHistoricalTransaction(array $overrides = []): Transaction
    {
        return Transaction::query()->create(array_merge([
            'reference' => 'PYL-'.now()->format('Ymd').'-HIST'.uniqid(),
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'verified_phone' => false,
            'created_at' => now()->subDays(10),
        ], $overrides));
    }
}
