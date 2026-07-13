<?php

namespace App\Console\Commands;

use App\Services\Finance\LedgerBackfillService;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Console\Command;

class PaylityLedgerBackfillCommand extends Command
{
    protected $signature = 'paylity:ledger-backfill
                            {--reference= : Backfill a single transaction reference}
                            {--since= : Only transactions created on or after this ISO date}
                            {--date= : Only transactions created on this date (YYYY-MM-DD)}
                            {--limit= : Maximum transactions to inspect}
                            {--dry-run : Inspect without making changes}
                            {--repair : Apply postings (default unless --dry-run)}';

    protected $description = 'Backfill ledger postings for historical transactions';

    public function __construct(
        private readonly LedgerBackfillService $ledgerBackfillService,
        private readonly SystemSettingsService $systemSettings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $repair = ! $dryRun;
        $defaultLimit = max(1, $this->systemSettings->getInt(
            SystemSettingKeys::FINANCIAL_BACKFILL_BATCH_SIZE,
            50,
        ));

        $limit = $this->option('limit') !== null
            ? max(1, (int) $this->option('limit'))
            : $defaultLimit;

        $summary = $this->ledgerBackfillService->backfill(
            reference: $this->option('reference') ? (string) $this->option('reference') : null,
            since: $this->option('since') ? (string) $this->option('since') : null,
            date: $this->option('date') ? (string) $this->option('date') : null,
            limit: $limit,
            dryRun: $dryRun,
            repair: $repair,
            verbose: $this->output->isVerbose(),
        );

        $this->table(
            ['Metric', 'Count'],
            collect($summary)
                ->filter(fn ($value, $key) => $key !== 'verbose_details' && $key !== 'diagnostics' && is_int($value))
                ->map(fn (int $count, string $metric) => [$metric, $count])
                ->values()
                ->all(),
        );

        if (! empty($summary['diagnostics']) && is_array($summary['diagnostics'])) {
            $diagnostics = $summary['diagnostics'];

            $this->newLine();
            $this->info('Ledger eligibility diagnostics');

            $this->table(
                ['Field', 'Value'],
                [
                    ['database_connection', (string) ($diagnostics['database_connection'] ?? '')],
                    ['database_name', (string) ($diagnostics['database_name'] ?? '—')],
                    ['total_transactions_in_database', (string) ($diagnostics['total_transactions_in_database'] ?? 0)],
                    ['eligible_in_database', (string) ($diagnostics['eligible_in_database'] ?? 0)],
                    ['paid_but_ineligible_status_count', (string) ($diagnostics['paid_but_ineligible_status_count'] ?? 0)],
                    ['root_cause', (string) ($diagnostics['root_cause'] ?? '')],
                ],
            );

            if (! empty($diagnostics['distinct_status_values']) && is_array($diagnostics['distinct_status_values'])) {
                $this->table(
                    ['Distinct status values'],
                    collect($diagnostics['distinct_status_values'])
                        ->map(fn (string $status) => [$status])
                        ->all(),
                );
            }

            if (! empty($diagnostics['status_breakdown']) && is_array($diagnostics['status_breakdown'])) {
                $this->table(
                    ['Status', 'Count'],
                    collect($diagnostics['status_breakdown'])
                        ->map(fn (int $count, string $status) => [$status, $count])
                        ->values()
                        ->all(),
                );
            }

            if (! empty($diagnostics['root_cause_detail'])) {
                $this->warn((string) $diagnostics['root_cause_detail']);
            }
        }

        if ($this->output->isVerbose() && ! empty($summary['verbose_details'])) {
            $this->table(
                ['Reference', 'Status', 'Reason', 'Details'],
                collect($summary['verbose_details'])->map(fn (array $row) => [
                    $row['reference'],
                    $row['status'],
                    $row['reason'],
                    isset($row['payment']) ? json_encode(['payment' => $row['payment'], 'fulfillment' => $row['fulfillment']]) : '',
                ])->all(),
            );
        }

        if ($dryRun) {
            $this->info('Dry run — no changes were applied.');
        }

        return self::SUCCESS;
    }
}
