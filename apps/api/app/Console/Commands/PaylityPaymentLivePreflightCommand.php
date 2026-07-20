<?php

namespace App\Console\Commands;

use App\Services\Launch\LaunchAuditService;
use App\Services\Launch\PaymentLivePreflightService;
use Illuminate\Console\Command;

class PaylityPaymentLivePreflightCommand extends Command
{
    protected $signature = 'paylity:payment-live-preflight
                            {--strict : Treat warnings as blockers}
                            {--json : Output JSON only}
                            {--reference= : Optional transaction reference to include in checks}';

    protected $description = 'Run read-only live payment preflight checks';

    public function __construct(
        private readonly PaymentLivePreflightService $paymentLivePreflightService,
        private readonly LaunchAuditService $launchAuditService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $report = $this->paymentLivePreflightService->run(
            strict: (bool) $this->option('strict'),
            reference: $this->option('reference') ? (string) $this->option('reference') : null,
            persist: true,
        );

        $this->launchAuditService->record(
            action: LaunchAuditService::ACTION_LIVE_PREFLIGHT,
            new: [
                'verdict' => $report['verdict'] ?? $report['status'],
                'strict' => (bool) $this->option('strict'),
            ],
        );

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            $this->info('PAYLITY Live Payment Preflight — '.($report['verdict'] ?? $report['status']));
            $this->table(
                ['Check', 'Status', 'Detail'],
                collect($report['checks'])->map(fn (array $row) => [
                    $row['name'], $row['status'], $row['detail'],
                ])->all(),
            );
            $this->line('Summary: '.json_encode($report['summary']));
        }

        return ($report['verdict'] ?? $report['status']) === PaymentLivePreflightService::STATUS_BLOCKED
            ? self::FAILURE
            : self::SUCCESS;
    }
}
