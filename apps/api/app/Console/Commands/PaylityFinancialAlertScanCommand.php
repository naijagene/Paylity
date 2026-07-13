<?php

namespace App\Console\Commands;

use App\Services\Finance\FinancialAlertService;
use Illuminate\Console\Command;

class PaylityFinancialAlertScanCommand extends Command
{
    protected $signature = 'paylity:financial-alert-scan
                            {--dry-run : Inspect candidate alerts without persisting or mutating data}';

    protected $description = 'Scan for financial operational alerts';

    public function __construct(
        private readonly FinancialAlertService $financialAlertService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $result = $this->financialAlertService->scan(dryRun: $dryRun);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Mode', $dryRun ? 'dry_run' : 'live'],
                ['Alerts detected', $result['totals']['alerts_detected']],
                ['Critical', $result['totals']['critical']],
                ['Warning', $result['totals']['warning']],
            ],
        );

        if ($result['alerts'] === []) {
            $this->info('No financial alerts detected.');

            if ($dryRun) {
                $this->info('Dry run — no changes were applied.');
            }

            return self::SUCCESS;
        }

        $this->table(
            ['Code', 'Severity', 'Message'],
            collect($result['alerts'])->map(fn (array $alert) => [
                $alert['code'],
                $alert['severity'],
                $alert['message'],
            ])->all(),
        );

        if ($this->output->isVerbose()) {
            $this->line('Verbose mode enabled (use -v, -vv, or -vvv).');
        }

        if ($dryRun) {
            $this->info('Dry run — no changes were applied.');
        }

        return self::SUCCESS;
    }
}
