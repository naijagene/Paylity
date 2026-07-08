<?php

namespace App\Services\Ops;

use App\Enums\TransactionStatus;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class OpsReportsService
{
    /**
     * @return array<string, int|float|string>
     */
    public function dailyReconciliation(?string $date = null): array
    {
        $day = $date ? Carbon::parse($date)->startOfDay() : today()->startOfDay();
        $end = $day->copy()->endOfDay();

        $query = fn (): Builder => Transaction::query()->whereBetween('created_at', [$day, $end]);

        $successfulPaymentStatuses = [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
        ];

        $totalTransactions = (int) $query()->count();
        $successfulPayments = (int) $query()->whereIn('status', $successfulPaymentStatuses)->count();
        $paymentFailed = (int) $query()->where('status', TransactionStatus::PAYMENT_FAILED)->count();
        $fulfillmentFailed = (int) $query()->where('status', TransactionStatus::FAILED)->count();
        $fulfilled = (int) $query()->where('status', TransactionStatus::FULFILLED)->count();
        $pendingFulfillment = (int) $query()->where('status', TransactionStatus::PAYMENT_SUCCESS)->count();

        $grossRevenue = (int) $query()->whereIn('status', $successfulPaymentStatuses)->sum('payable_amount');
        $productValue = (int) $query()->whereIn('status', $successfulPaymentStatuses)->sum('product_amount');
        $convenienceFees = (int) $query()->whereIn('status', $successfulPaymentStatuses)->sum('convenience_fee');
        $gatewayFees = (int) $query()->whereIn('status', $successfulPaymentStatuses)->sum('gateway_fee');

        return [
            'date' => $day->toDateString(),
            'total_transactions' => $totalTransactions,
            'successful_payments' => $successfulPayments,
            'payment_failed' => $paymentFailed,
            'fulfillment_failed' => $fulfillmentFailed,
            'fulfilled' => $fulfilled,
            'pending_fulfillment' => $pendingFulfillment,
            'gross_revenue' => $grossRevenue,
            'product_value' => $productValue,
            'convenience_fees' => $convenienceFees,
            'gateway_fees' => $gatewayFees,
            'success_rate' => $totalTransactions > 0
                ? round(($successfulPayments / $totalTransactions) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function failedTransactions(?string $dateFrom = null, ?string $dateTo = null, int $limit = 200): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : today()->startOfDay();
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : today()->endOfDay();

        return Transaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', [TransactionStatus::FAILED, TransactionStatus::PAYMENT_FAILED])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get([
                'reference',
                'product_type',
                'customer_phone',
                'product_amount',
                'payable_amount',
                'status',
                'failure_reason',
                'payment_reference',
                'created_at',
            ])
            ->map(fn (Transaction $transaction): array => [
                'reference' => $transaction->reference,
                'product_type' => $transaction->product_type,
                'customer_phone' => $transaction->customer_phone,
                'product_amount' => $transaction->product_amount,
                'payable_amount' => $transaction->payable_amount,
                'status' => $transaction->status,
                'failure_reason' => $transaction->failure_reason,
                'payment_reference' => $transaction->payment_reference,
                'created_at' => $transaction->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int|float|string>
     */
    public function settlementSummary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : today()->startOfDay();
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : today()->endOfDay();

        $successfulPaymentStatuses = [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
        ];

        $query = Transaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereIn('status', $successfulPaymentStatuses);

        $collected = (int) (clone $query)->sum('payable_amount');
        $productValue = (int) (clone $query)->sum('product_amount');
        $convenienceFees = (int) (clone $query)->sum('convenience_fee');
        $gatewayFees = (int) (clone $query)->sum('gateway_fee');
        $transactions = (int) (clone $query)->count();

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'transactions' => $transactions,
            'collected_amount' => $collected,
            'product_value' => $productValue,
            'convenience_fees' => $convenienceFees,
            'gateway_fees' => $gatewayFees,
            'estimated_net' => $collected - $gatewayFees,
        ];
    }

    /**
     * @return array<string, int|list<array<string, mixed>>>
     */
    public function retrySummary(?string $dateFrom = null, ?string $dateTo = null, int $limit = 200): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : today()->startOfDay();
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : today()->endOfDay();

        $attempts = FulfillmentAttempt::query()
            ->with(['transaction:reference,product_type,customer_phone,status'])
            ->where('attempt_number', '>', 1)
            ->whereBetween('attempted_at', [$from, $to])
            ->orderByDesc('attempted_at')
            ->limit($limit)
            ->get();

        $successful = $attempts->where('outcome', 'success')->count();
        $failed = $attempts->where('outcome', '!=', 'success')->count();

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'total_retries' => $attempts->count(),
            'successful_retries' => $successful,
            'failed_retries' => $failed,
            'items' => $attempts->map(fn (FulfillmentAttempt $attempt): array => [
                'transaction_reference' => $attempt->transaction?->reference,
                'product_type' => $attempt->transaction?->product_type,
                'customer_phone' => $attempt->transaction?->customer_phone,
                'transaction_status' => $attempt->transaction?->status,
                'attempt_number' => $attempt->attempt_number,
                'provider' => $attempt->provider,
                'outcome' => $attempt->outcome,
                'duration_ms' => $attempt->duration_ms,
                'failure_reason' => $attempt->failure_reason,
                'actor' => $attempt->actor,
                'attempted_at' => $attempt->attempted_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
