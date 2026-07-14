<?php

namespace App\Console\Commands;

use App\Services\Launch\LaunchPreflightService;
use Illuminate\Console\Command;

class PaylityLaunchPreflightCommand extends Command
{
    protected $signature = 'paylity:launch-preflight
                            {--environment=production : Target environment label}
                            {--json : Output JSON only}
                            {--strict : Treat warnings as blockers}
                            {--check-external : Include external provider reachability checks}
                            {--skip-external : Skip external provider reachability checks}
                            {--reference= : Optional smoke transaction reference}
                            {--dry-run : Alias for read-only preflight execution}';

    protected $description = 'Run production launch preflight checks';

    public function __construct(
        private readonly LaunchPreflightService $launchPreflightService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->launchPreflightService->run(
            environment: (string) $this->option('environment'),
            strict: (bool) $this->option('strict'),
            checkExternal: (bool) $this->option('check-external') && ! $this->option('skip-external'),
            reference: $this->option('reference') ? (string) $this->option('reference') : null,
        );

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->info('PAYLITY Launch Preflight — '.$report['status']);
            $this->table(
                ['Category', 'Check', 'Status', 'Detail'],
                collect($report['checks'])->map(fn (array $row) => [
                    $row['category'], $row['check'], $row['status'], $row['detail'],
                ])->all(),
            );
            $this->line('Summary: '.json_encode($report['summary']));
        }

        return $report['status'] === LaunchPreflightService::STATUS_BLOCKED ? self::FAILURE : self::SUCCESS;
    }
}
