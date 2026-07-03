<?php

namespace App\Console\Commands;

use App\Exceptions\VTPassConfigurationException;
use App\Services\Catalog\VTPassCatalogSyncService;
use App\Services\Fulfillment\VTPassService;
use Illuminate\Console\Command;

class PaylityCatalogSyncCommand extends Command
{
    protected $signature = 'paylity:catalog-sync {provider=vtpass : Catalog provider to sync}';

    protected $description = 'Sync provider product catalog variations from VTPass';

    public function __construct(
        private readonly VTPassService $vtpassService,
        private readonly VTPassCatalogSyncService $catalogSyncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $provider = strtolower(trim((string) $this->argument('provider')));

        if ($provider !== 'vtpass') {
            $this->error('Only vtpass catalog sync is supported.');

            return self::FAILURE;
        }

        $this->info('PAYLITY NG — Product Catalog Sync (VTPass)');

        if (! $this->vtpassService->isEnabled()) {
            $this->warn('FEATURE_VTPASS=false — enable it before syncing catalog variations.');

            return self::FAILURE;
        }

        try {
            $this->vtpassService->assertConfigured();
        } catch (VTPassConfigurationException) {
            $this->error('VTPass credentials are not configured.');

            return self::FAILURE;
        }

        $summary = $this->catalogSyncService->syncDataVariations();

        $this->table(
            ['Metric', 'Count'],
            [
                ['services synced', $summary['services_synced']],
                ['variations added', $summary['variations_added']],
                ['variations updated', $summary['variations_updated']],
                ['variations deactivated', $summary['variations_deactivated']],
                ['failures', count($summary['failures'])],
            ],
        );

        foreach ($summary['failures'] as $failure) {
            $this->warn($failure);
        }

        return $summary['failures'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
