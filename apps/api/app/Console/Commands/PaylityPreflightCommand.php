<?php

namespace App\Console\Commands;

use App\Support\Platform\PaylityEnvironmentValidator;
use Illuminate\Console\Command;

class PaylityPreflightCommand extends Command
{
    protected $signature = 'paylity:preflight';

    protected $description = 'Run PAYLITY NG release and staging readiness checks';

    public function __construct(
        private readonly PaylityEnvironmentValidator $environmentValidator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('PAYLITY NG — Release Preflight');
        $this->newLine();

        $results = $this->environmentValidator->validate();

        $rows = collect($results)->map(fn (array $result) => [
            $result['status'],
            $result['check'],
            $result['detail'],
        ])->all();

        $this->table(['Status', 'Check', 'Detail'], $rows);

        $summary = $this->environmentValidator->summary();

        $this->newLine();
        $this->line("Summary: {$summary['pass']} PASS, {$summary['warn']} WARN, {$summary['fail']} FAIL");

        if ($summary['fail'] > 0) {
            $this->error('Preflight failed. Resolve FAIL items before staging/production deployment.');
        } elseif ($summary['warn'] > 0) {
            $this->warn('Preflight passed with warnings. Review before deployment.');
        } else {
            $this->info('Preflight passed.');
        }

        return $this->environmentValidator->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}
