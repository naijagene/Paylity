<?php

namespace App\Console\Commands;

use App\Services\Finance\SettlementReconciliationService;
use Illuminate\Console\Command;

class PaylityReconcileSettlementsCommand extends Command
{
    protected $signature = 'paylity:reconcile-settlements
                            {--date= : Settlement date (YYYY-MM-DD)}
                            {--reference= : Reconcile a single transaction reference}
                            {--limit=50 : Maximum transactions to inspect}
                            {--dry-run : Inspect without making changes}
                            {--repair : Apply repairs (default unless --dry-run)}';

    protected $description = 'Reconcile Paystack settlement expectations and differences';

    public function __construct(
        private readonly SettlementReconciliationService $settlementReconciliationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $repair = ! $dryRun;

        $summary = $this->settlementReconciliationService->reconcile(
            date: $this->option('date') ? (string) $this->option('date') : null,
            reference: $this->option('reference') ? (string) $this->option('reference') : null,
            limit: max(1, (int) $this->option('limit')),
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
