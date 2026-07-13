<?php

namespace App\Services\Ops;

use App\Enums\OtpStatus;
use App\Enums\TransactionStatus;
use App\Models\OtpVerification;
use App\Models\Transaction;
use App\Services\Fulfillment\VtpassWalletBalanceService;
use App\Services\Otp\OtpService;
use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\HealthCheckService;
use App\Support\Fulfillment\VTPassEnvironment;
use App\Support\Platform\FeatureFlagKeys;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OpsMonitoringService
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly VtpassWalletBalanceService $walletBalanceService,
        private readonly HealthCheckService $healthCheckService,
        private readonly FeatureFlagService $featureFlags,
    ) {
    }

    /**
     * @return array<string, int|float|null|array<string, mixed>>
     */
    public function summary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : today()->startOfDay();
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : today()->endOfDay();

        $query = fn (): Builder => Transaction::query()
            ->whereBetween('created_at', [$from, $to]);

        $successfulPaymentStatuses = [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
        ];

        $revenue = (int) $query()->whereIn('status', $successfulPaymentStatuses)->sum('payable_amount');
        $transactions = (int) $query()->count();
        $failures = (int) $query()
            ->whereIn('status', [TransactionStatus::FAILED, TransactionStatus::PAYMENT_FAILED])
            ->count();
        $pending = (int) Transaction::query()
            ->where('status', TransactionStatus::PAYMENT_SUCCESS)
            ->count();

        $avgFulfillmentSeconds = $this->averageFulfillmentSeconds($from, $to);
        $queueMetrics = $this->queueMetrics();
        $wallet = $this->walletBalanceService->snapshot();
        $health = $this->healthCheckService->report();
        $checks = is_array($health['checks'] ?? null) ? $health['checks'] : [];

        return [
            'revenue' => $revenue,
            'transactions' => $transactions,
            'failures' => $failures,
            'pending' => $pending,
            'average_fulfillment_seconds' => $avgFulfillmentSeconds,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'queue' => $queueMetrics,
            'wallet' => $wallet,
            'vtpass' => [
                'status' => (string) ($checks['vtpass'] ?? 'unknown'),
                'enabled' => $this->featureFlags->isEnabled(FeatureFlagKeys::VTPASS),
                'environment' => VTPassEnvironment::mode(),
                'balance' => $wallet,
            ],
            'otp' => [
                'enabled' => $this->otpService->isEnabled(),
                'pending' => (int) OtpVerification::query()->where('status', OtpStatus::PENDING)->count(),
                'verified_today' => (int) OtpVerification::query()
                    ->where('status', OtpStatus::VERIFIED)
                    ->whereDate('verified_at', today())
                    ->count(),
                'failed_today' => (int) OtpVerification::query()
                    ->where('status', OtpStatus::FAILED)
                    ->whereDate('updated_at', today())
                    ->count(),
            ],
        ];
    }

    private function averageFulfillmentSeconds(Carbon $from, Carbon $to): ?float
    {
        $transactions = Transaction::query()
            ->where('status', TransactionStatus::FULFILLED)
            ->whereNotNull('fulfilled_at')
            ->whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'fulfilled_at']);

        if ($transactions->isEmpty()) {
            return null;
        }

        $totalSeconds = $transactions->sum(function (Transaction $transaction) {
            return $transaction->fulfilled_at?->diffInSeconds($transaction->created_at) ?? 0;
        });

        return round($totalSeconds / $transactions->count(), 1);
    }

    /**
     * @return array{connection: string, pending_jobs: int, failed_jobs: int, status: string}
     */
    private function queueMetrics(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $pendingJobs = 0;
        $failedJobs = 0;

        if ($connection === 'database' && Schema::hasTable('jobs')) {
            $pendingJobs = (int) DB::table('jobs')->count();
        }

        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = (int) DB::table('failed_jobs')->count();
        }

        $status = match (true) {
            $failedJobs > 0 => 'degraded',
            $connection === 'sync' => 'warning',
            default => 'ok',
        };

        return [
            'connection' => $connection,
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'status' => $status,
        ];
    }
}
