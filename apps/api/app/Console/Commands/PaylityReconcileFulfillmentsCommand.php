<?php

namespace App\Console\Commands;

use App\Services\Fulfillment\VtpassFulfillmentReconciliationService;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PaylityReconcileFulfillmentsCommand extends Command
{
    protected $signature = 'paylity:reconcile-fulfillments
                            {--reference= : Reconcile a single transaction reference}
                            {--since= : Only attempts started after this ISO date}
                            {--limit= : Maximum attempts to inspect}
                            {--dry-run : Inspect without making changes}
                            {--repair : Apply safe repairs (default true unless --dry-run)}';

    protected $description = 'Reconcile uncertain or submitted VTPass fulfillment attempts';

    public function __construct(
        private readonly VtpassFulfillmentReconciliationService $reconciliationService,
        private readonly SystemSettingsService $systemSettings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = (int) ($this->option('limit') ?: $this->systemSettings->getInt(
            SystemSettingKeys::RECONCILIATION_BATCH_SIZE,
            50,
        ));
        $since = $this->option('since')
            ? Carbon::parse((string) $this->option('since'))
            : null;
        $dryRun = (bool) $this->option('dry-run');
        $repair = ! $dryRun;

        $summary = $this->reconciliationService->reconcile(
            reference: $this->option('reference') ? (string) $this->option('reference') : null,
            since: $since,
            limit: max(1, $limit),
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

        return self::SUCCESS;
    }
}
