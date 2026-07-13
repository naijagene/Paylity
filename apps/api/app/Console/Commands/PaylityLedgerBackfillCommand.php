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
                            {--since= : Only transactions created after this ISO date}
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
            limit: $limit,
            dryRun: $dryRun,
            repair: $repair,
        );

        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn (int $count, string $metric) => [$metric, $count])->values()->all(),
        );

        if ($dryRun) {
            $this->info('Dry run — no changes were applied.');
        }

        if ($this->output->isVerbose()) {
            $this->line('Verbose mode enabled (use -v, -vv, or -vvv).');
        }

        return self::SUCCESS;
    }
}
