<?php

namespace App\Services\Ops;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Fulfillment\FulfillmentRetryService;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Platform\SystemSettingsService;
use App\Services\WebhookEventService;
use App\Support\Platform\SystemSettingKeys;

class OpsReliabilityService
{
    public function __construct(
        private readonly PaymentReconciliationService $paymentReconciliationService,
        private readonly FulfillmentRetryService $fulfillmentRetryService,
        private readonly WebhookEventService $webhookEventService,
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $paymentStaleMinutes = max(
            5,
            $this->systemSettings->getInt(SystemSettingKeys::PAYMENT_RECONCILE_STALE_MINUTES, 15),
        );
        $fulfillmentStaleMinutes = max(
            5,
            $this->systemSettings->getInt(SystemSettingKeys::FULFILLMENT_STALE_MINUTES, 30),
        );

        return [
            'webhooks' => $this->webhookEventService->metrics(),
            'reconciliation' => $this->paymentReconciliationService->staleCounts(
                $paymentStaleMinutes,
                $fulfillmentStaleMinutes,
            ),
            'retry_queue' => $this->fulfillmentRetryService->queueCounts(),
            'manual_review' => [
                'count' => (int) Transaction::query()->where('needs_manual_review', true)->count(),
                'items' => $this->manualReviewItems(),
            ],
            'retry_items' => $this->retryQueueItems(),
            'config' => [
                'payment_reconcile_stale_minutes' => $paymentStaleMinutes,
                'fulfillment_stale_minutes' => $fulfillmentStaleMinutes,
                'fulfillment_retry_max_attempts' => $this->fulfillmentRetryService->maxAttempts(),
                'fulfillment_retry_intervals_minutes' => $this->fulfillmentRetryService->retryIntervalsMinutes(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function manualReviewItems(int $limit = 15): array
    {
        return Transaction::query()
            ->where('needs_manual_review', true)
            ->orderByDesc('manual_review_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction) => $this->toQueueItem($transaction))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function retryQueueItems(int $limit = 15): array
    {
        return Transaction::query()
            ->where('needs_manual_review', false)
            ->whereIn('status', [TransactionStatus::PAYMENT_SUCCESS, TransactionStatus::FAILED])
            ->where(function ($query) {
                $query
                    ->where('next_fulfillment_retry_at', '<=', now())
                    ->orWhereNotNull('next_fulfillment_retry_at');
            })
            ->orderBy('next_fulfillment_retry_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction) => array_merge(
                $this->toQueueItem($transaction),
                [
                    'fulfillment_retry_count' => $transaction->fulfillment_retry_count,
                    'next_fulfillment_retry_at' => $transaction->next_fulfillment_retry_at?->toIso8601String(),
                ],
            ))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toQueueItem(Transaction $transaction): array
    {
        return [
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'customer_phone' => $transaction->customer_phone,
            'payable_amount' => $transaction->payable_amount,
            'status' => $transaction->status,
            'failure_reason' => $transaction->failure_reason,
            'manual_review_reason' => $transaction->manual_review_reason,
            'manual_review_at' => $transaction->manual_review_at?->toIso8601String(),
            'updated_at' => $transaction->updated_at?->toIso8601String(),
        ];
    }
}
