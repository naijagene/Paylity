<?php

namespace App\Services\Ops;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OpsMonitoringService
{
    /**
     * @return array<string, int|float|null>
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

        return [
            'revenue' => $revenue,
            'transactions' => $transactions,
            'failures' => $failures,
            'pending' => $pending,
            'average_fulfillment_seconds' => $avgFulfillmentSeconds,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
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
}
