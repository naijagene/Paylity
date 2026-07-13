<?php

namespace App\Console\Commands;

use App\Services\Finance\FinancialCloseService;
use Illuminate\Console\Command;

class PaylityFinancialCloseCommand extends Command
{
    protected $signature = 'paylity:financial-close
                            {--date= : Snapshot date (YYYY-MM-DD)}
                            {--dry-run : Preview without persisting}
                            {--repair : Persist snapshot (default unless --dry-run)}
                            {--force : Rebuild an unfinalized snapshot}';

    protected $description = 'Run daily financial close and snapshot generation';

    public function __construct(
        private readonly FinancialCloseService $financialCloseService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $repair = ! $dryRun;

        $result = $this->financialCloseService->close(
            date: $this->option('date') ? (string) $this->option('date') : null,
            dryRun: $dryRun,
            repair: $repair,
            force: (bool) $this->option('force'),
        );

        $this->info('Financial close status: '.($result['status'] ?? 'unknown'));

        if (isset($result['metrics']) && is_array($result['metrics'])) {
            $this->table(
                ['Metric', 'Value (kobo)'],
                collect($result['metrics'])
                    ->filter(fn ($value) => is_int($value) || is_float($value))
                    ->map(fn ($value, $key) => [$key, $value])
                    ->values()
                    ->all(),
            );
        }

        if ($dryRun) {
            $this->info('Dry run — no changes were applied.');
        }

        return self::SUCCESS;
    }
}
