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
                ->filter(fn ($value, $key) => $key !== 'verbose_details' && is_int($value))
                ->map(fn (int $count, string $metric) => [$metric, $count])
                ->values()
                ->all(),
        );

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
