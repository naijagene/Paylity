<?php

namespace Tests\Feature\Console;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Finance\LedgerBackfillService;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Pay032cLedgerBackfillDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_diagnose_reports_empty_database_root_cause(): void
    {
        $diagnostics = app(LedgerBackfillService::class)->diagnose();

        $this->assertSame(0, $diagnostics['total_transactions_in_database']);
        $this->assertSame(0, $diagnostics['eligible_in_database']);
        $this->assertSame([], $diagnostics['distinct_status_values']);
        $this->assertSame(LedgerBackfillService::ROOT_CAUSE_EMPTY_DATABASE, $diagnostics['root_cause']);
        $this->assertStringContainsString('Transaction table contains 0 rows', $diagnostics['root_cause_detail']);
    }

    public function test_diagnose_reports_distinct_status_values_and_status_mismatch(): void
    {
        Transaction::query()->create([
            'reference' => 'PYL-20260713-PENDING01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_PENDING,
            'payment_reference' => 'PSK-PENDING-001',
            'verified_phone' => false,
        ]);

        Transaction::query()->create([
            'reference' => 'PYL-20260713-CREATED01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234568',
            'product_amount' => 500,
            'convenience_fee' => 50,
            'gateway_fee' => 0,
            'payable_amount' => 550,
            'currency' => 'NGN',
            'status' => TransactionStatus::CREATED,
            'verified_phone' => false,
        ]);

        $diagnostics = app(LedgerBackfillService::class)->diagnose();

        $this->assertSame(2, $diagnostics['total_transactions_in_database']);
        $this->assertSame(0, $diagnostics['eligible_in_database']);
        $this->assertEqualsCanonicalizing(
            [TransactionStatus::PAYMENT_PENDING, TransactionStatus::CREATED],
            $diagnostics['distinct_status_values'],
        );
        $this->assertSame(1, $diagnostics['paid_but_ineligible_status_count']);
        $this->assertSame(LedgerBackfillService::ROOT_CAUSE_STATUS_MISMATCH, $diagnostics['root_cause']);
        $this->assertStringContainsString('payment_pending=1', $diagnostics['root_cause_detail']);
    }

    public function test_backfill_command_prints_status_breakdown_and_root_cause(): void
    {
        Transaction::query()->create([
            'reference' => 'PYL-20260713-FULFILLED01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234569',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'payment_reference' => 'PSK-FULFILLED-001',
            'fulfilled_at' => now()->subDay(),
            'verified_phone' => false,
        ]);

        $this->artisan('paylity:ledger-backfill', [
            '--dry-run' => true,
            '--limit' => 5,
        ])
            ->expectsOutputToContain('Ledger eligibility diagnostics')
            ->expectsOutputToContain('fulfilled')
            ->expectsOutputToContain('Eligible transactions are present')
            ->assertSuccessful();
    }

    public function test_diagnose_reports_already_posted_root_cause_when_no_candidates_remain(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260713-POSTED01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234570',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'payment_reference' => 'PSK-POSTED-001',
            'fulfilled_at' => now()->subDay(),
            'verified_phone' => false,
        ]);

        app(LedgerBackfillService::class)->backfill(
            reference: $transaction->reference,
            dryRun: false,
            repair: true,
        );

        $summary = app(LedgerBackfillService::class)->backfill(
            limit: 5,
            dryRun: true,
        );

        $this->assertSame(1, $summary['eligible_in_database']);
        $this->assertSame(0, $summary['candidates_selected']);
        $this->assertSame(LedgerBackfillService::ROOT_CAUSE_ALREADY_POSTED, $summary['diagnostics']['root_cause']);
    }
}
