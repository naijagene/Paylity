<?php

namespace App\Services\Launch;

use App\Models\DailyFinancialSnapshot;
use App\Services\Fulfillment\VtpassWalletBalanceService;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;

class LaunchBlockersService
{
    public function __construct(
        private readonly SchedulerHeartbeatService $schedulerHeartbeatService,
        private readonly PaystackModeInspector $paystackModeInspector,
        private readonly VtpassModeInspector $vtpassModeInspector,
        private readonly VtpassWalletBalanceService $walletBalanceService,
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $checks
     * @return list<array{code: string, message: string, severity: string}>
     */
    public function fromChecks(array $checks, string $environment = 'production'): array
    {
        $blockers = [];

        foreach ($checks as $check) {
            if (($check['status'] ?? '') !== 'FAIL') {
                continue;
            }

            $name = (string) ($check['name'] ?? $check['check'] ?? 'unknown');
            $message = (string) ($check['message'] ?? $check['detail'] ?? 'Check failed.');

            $blockers[] = [
                'code' => strtoupper(str_replace(' ', '_', $name)),
                'message' => $message,
                'severity' => (string) ($check['severity'] ?? 'critical'),
            ];
        }

        return array_merge($blockers, $this->contextualBlockers($environment));
    }

    /**
     * @return list<array{code: string, message: string, severity: string}>
     */
    public function contextualBlockers(string $environment = 'production'): array
    {
        $blockers = [];
        $isProduction = $environment === 'production';

        if ($isProduction && (bool) config('app.debug')) {
            $blockers[] = [
                'code' => 'APP_DEBUG_ENABLED',
                'message' => 'APP_DEBUG is enabled in a production environment.',
                'severity' => 'critical',
            ];
        }

        $scheduler = $this->schedulerHeartbeatService->snapshot();
        $schedulerStatus = (string) ($scheduler['status'] ?? SchedulerHeartbeatService::STATUS_UNKNOWN);

        if ($schedulerStatus === SchedulerHeartbeatService::STATUS_UNKNOWN) {
            $blockers[] = [
                'code' => 'SCHEDULER_HEARTBEAT_MISSING',
                'message' => 'Scheduler heartbeat has never been recorded.',
                'severity' => 'critical',
            ];
        } elseif ($schedulerStatus === SchedulerHeartbeatService::STATUS_CRITICAL) {
            $blockers[] = [
                'code' => 'SCHEDULER_HEARTBEAT_CRITICAL',
                'message' => 'Scheduler heartbeat is critically stale.',
                'severity' => 'critical',
            ];
        }

        $paystack = $this->paystackModeInspector->inspect();
        if ($isProduction && ($paystack['mode'] ?? '') === 'test') {
            $blockers[] = [
                'code' => 'PAYSTACK_TEST_MODE',
                'message' => 'Paystack is configured in TEST mode.',
                'severity' => 'critical',
            ];
        }

        if (($paystack['callback_url'] ?? '') === '') {
            $blockers[] = [
                'code' => 'CALLBACK_URL_MISSING',
                'message' => 'No Paystack callback URL is configured.',
                'severity' => 'critical',
            ];
        }

        if (! ($paystack['webhook_route_exists'] ?? false)) {
            $blockers[] = [
                'code' => 'WEBHOOK_ROUTE_MISSING',
                'message' => 'Paystack webhook route is not registered.',
                'severity' => 'critical',
            ];
        }

        $vtpass = $this->vtpassModeInspector->inspect();
        if ($isProduction && ($vtpass['mode'] ?? 'sandbox') === 'sandbox') {
            $blockers[] = [
                'code' => 'VTPASS_SANDBOX_MODE',
                'message' => 'VTPass is configured in SANDBOX mode.',
                'severity' => 'critical',
            ];
        }

        $wallet = $this->walletBalanceService->snapshot();
        $walletHealth = (string) ($wallet['health'] ?? VtpassWalletBalanceService::HEALTH_UNKNOWN);

        if (in_array($walletHealth, [VtpassWalletBalanceService::HEALTH_WARNING, VtpassWalletBalanceService::HEALTH_CRITICAL], true)) {
            $blockers[] = [
                'code' => 'WALLET_BELOW_THRESHOLD',
                'message' => 'VTPass wallet balance is below the configured threshold.',
                'severity' => $walletHealth === VtpassWalletBalanceService::HEALTH_CRITICAL ? 'critical' : 'warning',
            ];
        }

        if ($isProduction && ! $this->hasCompletedFinancialClose()) {
            $blockers[] = [
                'code' => 'NO_FINANCIAL_CLOSE',
                'message' => 'No completed financial close snapshot is on record.',
                'severity' => 'warning',
            ];
        }

        return $this->deduplicate($blockers);
    }

    private function hasCompletedFinancialClose(): bool
    {
        return DailyFinancialSnapshot::query()
            ->whereNotNull('finalized_at')
            ->whereIn('status', ['finalized', 'finalized_with_exceptions'])
            ->exists();
    }

    /**
     * @param  list<array{code: string, message: string, severity: string}>  $blockers
     * @return list<array{code: string, message: string, severity: string}>
     */
    private function deduplicate(array $blockers): array
    {
        $seen = [];

        return collect($blockers)
            ->filter(function (array $blocker) use (&$seen): bool {
                $code = $blocker['code'];

                if (isset($seen[$code])) {
                    return false;
                }

                $seen[$code] = true;

                return true;
            })
            ->values()
            ->all();
    }
}
