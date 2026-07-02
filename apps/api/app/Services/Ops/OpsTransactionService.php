<?php

namespace App\Services\Ops;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class OpsTransactionService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Transaction::query()->orderByDesc('created_at');

        if (! empty($filters['reference'])) {
            $query->where('reference', 'like', '%'.$filters['reference'].'%');
        }

        if (! empty($filters['phone'])) {
            $query->where('customer_phone', 'like', '%'.$filters['phone'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['product_type'])) {
            $query->where('product_type', $filters['product_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 100);

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListItem(Transaction $transaction): array
    {
        return [
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'customer_phone' => $transaction->customer_phone,
            'product_amount' => $transaction->product_amount,
            'payable_amount' => $transaction->payable_amount,
            'status' => $transaction->status,
            'failure_reason' => $transaction->failure_reason,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailResponse(Transaction $transaction): array
    {
        return [
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'customer_phone' => $transaction->customer_phone,
            'customer_email' => $transaction->customer_email,
            'customer_name' => $transaction->customer_name,
            'product_amount' => $transaction->product_amount,
            'convenience_fee' => $transaction->convenience_fee,
            'gateway_fee' => $transaction->gateway_fee,
            'payable_amount' => $transaction->payable_amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'payment_provider' => $transaction->payment_provider,
            'payment_reference' => $transaction->payment_reference,
            'payment_authorization_url' => $transaction->payment_authorization_url,
            'fulfillment_provider' => $transaction->fulfillment_provider,
            'fulfillment_reference' => $transaction->fulfillment_reference,
            'failure_reason' => $transaction->failure_reason,
            'request_payload' => $transaction->request_payload,
            'response_payload' => $transaction->response_payload,
            'ip_address' => $transaction->ip_address,
            'user_agent' => $transaction->user_agent,
            'verified_phone' => $transaction->verified_phone,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'updated_at' => $transaction->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function summaryForToday(): array
    {
        $todayQuery = fn (): Builder => Transaction::query()->whereDate('created_at', today());

        $successfulPaymentStatuses = [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
        ];

        return [
            'total_transactions_today' => (int) $todayQuery()->count(),
            'successful_payments_today' => (int) $todayQuery()
                ->whereIn('status', $successfulPaymentStatuses)
                ->count(),
            'fulfilled_today' => (int) $todayQuery()
                ->where('status', TransactionStatus::FULFILLED)
                ->count(),
            'failed_today' => (int) $todayQuery()
                ->whereIn('status', [TransactionStatus::FAILED, TransactionStatus::PAYMENT_FAILED])
                ->count(),
            'pending_fulfillment' => (int) Transaction::query()
                ->where('status', TransactionStatus::PAYMENT_SUCCESS)
                ->count(),
            'total_convenience_fees_today' => (int) $todayQuery()->sum('convenience_fee'),
        ];
    }
}
