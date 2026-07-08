<?php

namespace App\Services\Ops;

use App\Enums\OtpStatus;
use App\Enums\TransactionStatus;
use App\Models\OtpVerification;
use App\Models\Transaction;
use App\Services\Otp\OtpService;
use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\HealthCheckService;
use App\Services\Platform\PlatformStatusService;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;
use Carbon\Carbon;

class OpsDashboardService
{
    public function __construct(
        private readonly OpsMonitoringService $opsMonitoringService,
        private readonly HealthCheckService $healthCheckService,
        private readonly PlatformStatusService $platformStatusService,
        private readonly SystemSettingsService $systemSettings,
        private readonly FeatureFlagService $featureFlags,
        private readonly OtpService $otpService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $todayMonitoring = $this->opsMonitoringService->summary();
        $health = $this->healthCheckService->report();
        $platformStatus = $this->platformStatusService->publicStatus();

        $successfulPaymentStatuses = $this->successfulPaymentStatuses();
        $todayFrom = today()->startOfDay();
        $todayTo = today()->endOfDay();

        $todayTransactions = (int) Transaction::query()
            ->whereBetween('created_at', [$todayFrom, $todayTo])
            ->count();

        $todaySuccessful = (int) Transaction::query()
            ->whereBetween('created_at', [$todayFrom, $todayTo])
            ->whereIn('status', $successfulPaymentStatuses)
            ->count();

        $averageTransaction = $todaySuccessful > 0
            ? (int) round(
                (int) Transaction::query()
                    ->whereBetween('created_at', [$todayFrom, $todayTo])
                    ->whereIn('status', $successfulPaymentStatuses)
                    ->avg('payable_amount')
            )
            : 0;

        return [
            'enabled' => true,
            'refreshed_at' => now()->toIso8601String(),
            'executive' => [
                'revenue_today' => (int) ($todayMonitoring['revenue'] ?? 0),
                'transactions_today' => (int) ($todayMonitoring['transactions'] ?? 0),
                'success_rate' => $this->successRate($todaySuccessful, $todayTransactions),
                'pending' => (int) ($todayMonitoring['pending'] ?? 0),
                'failed' => (int) ($todayMonitoring['failures'] ?? 0),
                'average_transaction' => $averageTransaction,
                'average_fulfillment_seconds' => $todayMonitoring['average_fulfillment_seconds'] ?? null,
                'queue_size' => (int) ($todayMonitoring['queue']['pending_jobs'] ?? 0),
                'api_health' => (string) ($health['status'] ?? 'unknown'),
            ],
            'revenue' => $this->revenueAnalytics(),
            'transactions' => $this->transactionAnalytics(),
            'providers' => $this->providerHealth($health),
            'fraud' => $this->fraudMonitoring(),
            'alerts' => $this->buildAlerts($health, $todayMonitoring, $platformStatus),
            'platform' => $platformStatus,
        ];
    }

    /**
     * @return array<string, array<string, int|float>>
     */
    private function revenueAnalytics(): array
    {
        return [
            'today' => $this->revenueForPeriod(today()->startOfDay(), today()->endOfDay()),
            'yesterday' => $this->revenueForPeriod(
                today()->subDay()->startOfDay(),
                today()->subDay()->endOfDay(),
            ),
            'week' => $this->revenueForPeriod(
                today()->startOfWeek()->startOfDay(),
                today()->endOfDay(),
            ),
            'month' => $this->revenueForPeriod(
                today()->startOfMonth()->startOfDay(),
                today()->endOfDay(),
            ),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function revenueForPeriod(Carbon $from, Carbon $to): array
    {
        $successfulPaymentStatuses = $this->successfulPaymentStatuses();

        $query = Transaction::query()->whereBetween('created_at', [$from, $to]);
        $successfulQuery = (clone $query)->whereIn('status', $successfulPaymentStatuses);

        $totalRevenue = (int) (clone $successfulQuery)->sum('payable_amount');
        $platformFees = (int) (clone $successfulQuery)->sum('convenience_fee');
        $gatewayCharges = (int) (clone $successfulQuery)->sum('gateway_fee');
        $transactions = (int) (clone $query)->count();

        return [
            'total_revenue' => $totalRevenue,
            'platform_fees' => $platformFees,
            'gateway_charges' => $gatewayCharges,
            'net_revenue' => $totalRevenue - $gatewayCharges,
            'transactions' => $transactions,
        ];
    }

    /**
     * @return array<string, array<string, int|float>>
     */
    private function transactionAnalytics(): array
    {
        $from = today()->startOfDay();
        $to = today()->endOfDay();
        $successfulPaymentStatuses = $this->successfulPaymentStatuses();

        $totals = [];
        $grandTotal = 0;

        foreach (['airtime', 'data', 'electricity'] as $productType) {
            $count = (int) Transaction::query()
                ->whereBetween('created_at', [$from, $to])
                ->where('product_type', $productType)
                ->count();

            $revenue = (int) Transaction::query()
                ->whereBetween('created_at', [$from, $to])
                ->where('product_type', $productType)
                ->whereIn('status', $successfulPaymentStatuses)
                ->sum('payable_amount');

            $totals[$productType] = [
                'count' => $count,
                'revenue' => $revenue,
            ];
            $grandTotal += $count;
        }

        foreach ($totals as $productType => $values) {
            $totals[$productType]['percentage'] = $grandTotal > 0
                ? round(($values['count'] / $grandTotal) * 100, 1)
                : 0.0;
        }

        $totals['total'] = $grandTotal;

        return $totals;
    }

    /**
     * @param  array<string, mixed>  $health
     * @return array<string, array<string, string|int|bool>>
     */
    private function providerHealth(array $health): array
    {
        $checks = is_array($health['checks'] ?? null) ? $health['checks'] : [];
        $queue = is_array($checks['queue'] ?? null) ? $checks['queue'] : [];

        return [
            'paystack' => [
                'status' => (string) ($checks['paystack'] ?? 'unknown'),
                'enabled' => $this->featureFlags->isEnabled(FeatureFlagKeys::PAYSTACK),
            ],
            'vtpass' => [
                'status' => (string) ($checks['vtpass'] ?? 'unknown'),
                'enabled' => $this->featureFlags->isEnabled(FeatureFlagKeys::VTPASS),
            ],
            'database' => [
                'status' => (string) ($checks['database'] ?? 'unknown'),
            ],
            'cache' => [
                'status' => (string) ($checks['cache'] ?? 'unknown'),
            ],
            'queue' => [
                'status' => (string) ($queue['status'] ?? 'unknown'),
                'pending_jobs' => (int) ($queue['pending_jobs'] ?? 0),
                'failed_jobs' => (int) ($queue['failed_jobs'] ?? 0),
            ],
            'mail' => [
                'status' => (string) ($checks['mail'] ?? 'unknown'),
            ],
            'api' => [
                'status' => (string) ($health['status'] ?? 'unknown'),
            ],
        ];
    }

    /**
     * @return array<string, int|bool>
     */
    private function fraudMonitoring(): array
    {
        $todayStart = today()->startOfDay();

        $otpFailedToday = (int) OtpVerification::query()
            ->where('status', OtpStatus::FAILED)
            ->where('updated_at', '>=', $todayStart)
            ->count();

        $otpPending = (int) OtpVerification::query()
            ->where('status', OtpStatus::PENDING)
            ->count();

        $failedVerifications = (int) OtpVerification::query()
            ->where('status', OtpStatus::FAILED)
            ->where('updated_at', '>=', $todayStart)
            ->count();

        $paymentFailedToday = (int) Transaction::query()
            ->whereDate('created_at', today())
            ->where('status', TransactionStatus::PAYMENT_FAILED)
            ->count();

        return [
            'otp_enabled' => $this->otpService->isEnabled(),
            'otp_failures_today' => $otpFailedToday,
            'otp_pending' => $otpPending,
            'failed_verifications' => $failedVerifications,
            'blocked_transactions' => $paymentFailedToday,
            'daily_limit_hits' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $health
     * @param  array<string, mixed>  $monitoring
     * @param  array<string, mixed>  $platformStatus
     * @return list<array<string, string>>
     */
    private function buildAlerts(array $health, array $monitoring, array $platformStatus): array
    {
        $alerts = [];

        if ($this->systemSettings->getBool(SystemSettingKeys::INCIDENT_MODE)) {
            $alerts[] = [
                'severity' => 'critical',
                'code' => 'INCIDENT_MODE',
                'message' => 'Incident mode is enabled. Checkout is paused.',
            ];
        }

        if ($this->systemSettings->getBool(SystemSettingKeys::MAINTENANCE_MODE)) {
            $alerts[] = [
                'severity' => 'warning',
                'code' => 'MAINTENANCE_MODE',
                'message' => 'Maintenance mode is enabled. Checkout is disabled.',
            ];
        }

        if (($health['status'] ?? 'ok') !== 'ok') {
            $alerts[] = [
                'severity' => ($health['status'] ?? '') === 'unhealthy' ? 'critical' : 'warning',
                'code' => 'API_HEALTH_DEGRADED',
                'message' => 'API health is '.(string) ($health['status'] ?? 'degraded').'.',
            ];
        }

        $checks = is_array($health['checks'] ?? null) ? $health['checks'] : [];

        if (($checks['database'] ?? 'ok') !== 'ok') {
            $alerts[] = [
                'severity' => 'critical',
                'code' => 'DATABASE_DEGRADED',
                'message' => 'Database health check failed.',
            ];
        }

        if (($checks['paystack'] ?? 'ok') === 'failed') {
            $alerts[] = [
                'severity' => 'critical',
                'code' => 'PAYSTACK_FAILURE',
                'message' => 'Paystack provider check failed.',
            ];
        }

        if (($checks['vtpass'] ?? 'ok') === 'failed') {
            $alerts[] = [
                'severity' => 'critical',
                'code' => 'VTPASS_FAILURE',
                'message' => 'VTPass provider check failed.',
            ];
        }

        $queue = is_array($monitoring['queue'] ?? null) ? $monitoring['queue'] : [];
        $pendingJobs = (int) ($queue['pending_jobs'] ?? 0);
        $failedJobs = (int) ($queue['failed_jobs'] ?? 0);

        if ($failedJobs > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'code' => 'QUEUE_FAILED_JOBS',
                'message' => "{$failedJobs} failed queue job(s) require attention.",
            ];
        }

        if ($pendingJobs >= 25) {
            $alerts[] = [
                'severity' => 'warning',
                'code' => 'QUEUE_BACKLOG',
                'message' => "Queue backlog detected ({$pendingJobs} pending jobs).",
            ];
        }

        $pendingFulfillment = (int) ($monitoring['pending'] ?? 0);

        if ($pendingFulfillment >= 10) {
            $alerts[] = [
                'severity' => 'warning',
                'code' => 'FULFILLMENT_BACKLOG',
                'message' => "{$pendingFulfillment} transactions are awaiting fulfillment.",
            ];
        }

        if (($platformStatus['checkout_enabled'] ?? true) === false && empty($alerts)) {
            $alerts[] = [
                'severity' => 'warning',
                'code' => 'CHECKOUT_DISABLED',
                'message' => (string) ($platformStatus['message'] ?? 'Checkout is currently disabled.'),
            ];
        }

        return $alerts;
    }

    private function successRate(int $successful, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($successful / $total) * 100, 1);
    }

    /**
     * @return list<string>
     */
    private function successfulPaymentStatuses(): array
    {
        return [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
        ];
    }
}
