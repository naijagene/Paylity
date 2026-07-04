<?php

namespace App\Console\Commands;

use App\Services\Catalog\VariationClassificationService;
use Illuminate\Console\Command;

class PaylityCatalogClassifyCommand extends Command
{
    protected $signature = 'paylity:catalog-classify
                            {--force-overrides : Reclassify variations with manual display overrides}';

    protected $description = 'Reclassify provider variations for customer-facing checkout display';

    public function handle(VariationClassificationService $classificationService): int
    {
        $forceOverrides = (bool) $this->option('force-overrides');

        if ($forceOverrides) {
            $this->warn('Manual display overrides will be overwritten.');
        }

        $summary = $classificationService->reclassifyAll($forceOverrides);

        $this->info('Catalog classification complete.');
        $this->table(
            ['Metric', 'Count'],
            collect($summary)->map(fn (int $value, string $key) => [
                str_replace('_', ' ', ucfirst($key)),
                $value,
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
