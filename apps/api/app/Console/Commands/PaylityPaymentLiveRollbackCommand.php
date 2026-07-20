<?php

namespace App\Console\Commands;

use App\Services\Launch\LaunchAuditService;
use App\Services\Launch\LaunchModeService;
use Illuminate\Console\Command;

class PaylityPaymentLiveRollbackCommand extends Command
{
    protected $signature = 'paylity:payment-live-rollback
                            {--maintenance : Switch to maintenance mode}
                            {--soft-launch : Restore soft launch mode}
                            {--confirm= : Required confirmation phrase}';

    protected $description = 'Emergency rollback for live payment operations';

    public function __construct(
        private readonly LaunchModeService $launchModeService,
        private readonly LaunchAuditService $launchAuditService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $targetMode = $this->resolveTargetMode();
        if ($targetMode === null) {
            $this->error('Specify exactly one of --maintenance or --soft-launch.');

            return self::FAILURE;
        }

        $confirm = (string) $this->option('confirm');
        $expected = $targetMode === LaunchModeService::MODE_MAINTENANCE
            ? 'ENTER-MAINTENANCE'
            : 'RESTORE-SOFT-LAUNCH';

        if ($confirm !== $expected) {
            $this->error("Confirmation required: --confirm={$expected}");

            return self::FAILURE;
        }

        $previous = $this->launchModeService->snapshot();
        $next = $this->launchModeService->setMode($targetMode);

        $action = $targetMode === LaunchModeService::MODE_MAINTENANCE
            ? LaunchAuditService::ACTION_MAINTENANCE_ENTERED
            : LaunchAuditService::ACTION_SOFT_LAUNCH_RESTORED;

        $this->launchAuditService->record(
            action: LaunchAuditService::ACTION_LIVE_ROLLBACK,
            previous: ['launch_mode' => $previous['mode'] ?? null],
            new: ['launch_mode' => $next['mode'] ?? $targetMode],
            operator: 'cli',
            reason: 'Emergency live payment rollback',
        );

        $this->launchAuditService->record(
            action: $action,
            previous: ['launch_mode' => $previous['mode'] ?? null],
            new: ['launch_mode' => $next['mode'] ?? $targetMode],
            operator: 'cli',
            reason: 'Emergency live payment rollback',
        );

        $this->info('Launch mode changed from '.($previous['mode'] ?? 'unknown').' to '.($next['mode'] ?? $targetMode).'.');
        $this->newLine();
        $this->line('Next operational steps:');
        $this->line('- Callback and webhook processing remain enabled.');
        $this->line('- Paid transactions can still be fulfilled or recovered.');
        $this->line('- New checkout initialization is blocked only in maintenance mode.');
        $this->line('- Run `php artisan paylity:payment-live-preflight --strict` before re-enabling live checkout.');
        $this->line('- Review Ops Go-Live Center and Finance Center for in-flight transactions.');

        return self::SUCCESS;
    }

    private function resolveTargetMode(): ?string
    {
        $maintenance = (bool) $this->option('maintenance');
        $softLaunch = (bool) $this->option('soft-launch');

        if ($maintenance xor $softLaunch) {
            return $maintenance ? LaunchModeService::MODE_MAINTENANCE : LaunchModeService::MODE_SOFT_LAUNCH;
        }

        return null;
    }
}
