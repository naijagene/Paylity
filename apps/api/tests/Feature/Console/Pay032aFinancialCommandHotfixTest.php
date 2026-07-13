<?php

namespace Tests\Feature\Console;

use App\Models\DailyFinancialSnapshot;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use App\Models\TransactionFinancial;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Pay032aFinancialCommandHotfixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LedgerAccountSeeder::class);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_ledger_backfill_command_boots_without_duplicate_verbose_option(): void
    {
        $this->artisan('paylity:ledger-backfill', [
            '--dry-run' => true,
            '--limit' => 50,
        ])->assertSuccessful();
    }

    public function test_reconcile_settlements_command_boots_without_duplicate_verbose_option(): void
    {
        $this->artisan('paylity:reconcile-settlements', [
            '--dry-run' => true,
        ])->assertSuccessful();
    }

    public function test_built_in_verbose_flag_still_works_for_finance_commands(): void
    {
        $this->artisan('paylity:ledger-backfill', [
            '--dry-run' => true,
            '--limit' => 1,
            '-v' => true,
        ])->assertSuccessful();

        $this->artisan('paylity:reconcile-settlements', [
            '--dry-run' => true,
            '-v' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Verbose mode enabled');
    }

    public function test_financial_alert_scan_accepts_dry_run_option(): void
    {
        $this->artisan('paylity:financial-alert-scan', [
            '--dry-run' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('dry_run')
            ->expectsOutputToContain('Dry run — no changes were applied.');
    }

    public function test_alert_dry_run_performs_zero_writes(): void
    {
        $before = $this->mutableRecordCounts();

        $this->artisan('paylity:financial-alert-scan', [
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame($before, $this->mutableRecordCounts());
    }

    public function test_normal_alert_scan_reports_expected_alerts_without_mutations(): void
    {
        $before = $this->mutableRecordCounts();

        $this->artisan('paylity:financial-alert-scan')
            ->assertSuccessful()
            ->expectsOutputToContain('DAILY_CLOSE_NOT_COMPLETED');

        $this->assertSame($before, $this->mutableRecordCounts());
    }

    public function test_repeated_alert_scan_remains_idempotent(): void
    {
        $before = $this->mutableRecordCounts();

        $this->artisan('paylity:financial-alert-scan')->assertSuccessful();
        $this->artisan('paylity:financial-alert-scan')->assertSuccessful();

        $this->assertSame($before, $this->mutableRecordCounts());
    }

    public function test_financial_alert_service_scan_returns_structured_result(): void
    {
        $result = app(\App\Services\Finance\FinancialAlertService::class)->scan(dryRun: true);

        $this->assertArrayHasKey('alerts', $result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertTrue($result['dry_run']);
        $this->assertGreaterThan(0, $result['totals']['alerts_detected']);
    }

    /**
     * @return array<string, int>
     */
    private function mutableRecordCounts(): array
    {
        return [
            'transactions' => Transaction::query()->count(),
            'ledger_transactions' => LedgerTransaction::query()->count(),
            'ledger_entries' => LedgerEntry::query()->count(),
            'transaction_financials' => TransactionFinancial::query()->count(),
            'daily_financial_snapshots' => DailyFinancialSnapshot::query()->count(),
        ];
    }
}
