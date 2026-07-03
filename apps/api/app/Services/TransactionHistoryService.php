<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TransactionHistoryService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Transaction::query()->orderByDesc('created_at');

        if (! empty($filters['phone'])) {
            $query->where('customer_phone', $filters['phone']);
        }

        if (! empty($filters['status_group'])) {
            $this->applyStatusGroup($query, (string) $filters['status_group']);
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

        $perPage = min(max((int) ($filters['per_page'] ?? 20), 1), 50);

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
            'product_label' => $this->productLabel($transaction->product_type),
            'customer_phone' => $transaction->customer_phone,
            'payable_amount' => $transaction->payable_amount,
            'status' => $transaction->status,
            'status_group' => $this->statusGroup($transaction->status),
            'failure_reason' => $transaction->failure_reason,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'updated_at' => $transaction->updated_at?->toIso8601String(),
        ];
    }

    private function applyStatusGroup(Builder $query, string $statusGroup): void
    {
        match ($statusGroup) {
            'delivered' => $query->where('status', TransactionStatus::FULFILLED),
            'processing' => $query->whereIn('status', [
                TransactionStatus::PAYMENT_PENDING,
                TransactionStatus::PAYMENT_SUCCESS,
                TransactionStatus::FULFILLMENT_PENDING,
            ]),
            'failed' => $query->whereIn('status', [
                TransactionStatus::FAILED,
                TransactionStatus::PAYMENT_FAILED,
            ]),
            default => null,
        };
    }

    private function statusGroup(string $status): string
    {
        return match ($status) {
            TransactionStatus::FULFILLED => 'delivered',
            TransactionStatus::FAILED, TransactionStatus::PAYMENT_FAILED => 'failed',
            default => 'processing',
        };
    }

    private function productLabel(string $productType): string
    {
        return match ($productType) {
            'airtime' => 'Airtime',
            'data' => 'Data',
            'electricity' => 'Electricity',
            default => ucfirst($productType),
        };
    }
}
