<?php

namespace App\Services\Ops;

use App\Enums\FulfillmentAttemptStatus;
use App\Enums\TransactionStatus;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;
use App\Models\TransactionEvent;
use App\Services\Fulfillment\ExactOnceFulfillmentService;
use App\Services\Fulfillment\FulfillmentRetryService;
use App\Services\Fulfillment\VtpassFulfillmentReconciliationService;
use App\Services\Payments\PaymentReconciliationService;
use App\Services\Platform\SystemSettingsService;
use App\Services\TransactionEventService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Carbon;

class OpsReconciliationService
{
    public function __construct(
        private readonly PaymentReconciliationService $paymentReconciliationService,
        private readonly VtpassFulfillmentReconciliationService $fulfillmentReconciliationService,
        private readonly ExactOnceFulfillmentService $exactOnceFulfillmentService,
        private readonly FulfillmentRetryService $fulfillmentRetryService,
        private readonly TransactionEventService $transactionEventService,
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $paymentStale = $this->paymentStaleMinutes();
        $fulfillmentStale = $this->fulfillmentStaleMinutes();

        return [
            'summary' => [
                'paid_unfulfilled' => $this->countPaidUnfulfilled($paymentStale),
                'stale_payment_pending' => $this->paymentReconciliationService
                    ->staleCounts($paymentStale, $fulfillmentStale)['stale_payment_pending'],
                'uncertain_provider_outcomes' => $this->fulfillmentReconciliationService
                    ->staleCounts()['uncertain_attempts'],
                'retry_due' => $this->fulfillmentRetryService->queueCounts()['due_now'],
                'retry_exhausted' => (int) FulfillmentAttempt::query()
                    ->where('status', FulfillmentAttemptStatus::DEAD_LETTER)
                    ->count(),
                'manual_review' => (int) Transaction::query()
                    ->where('needs_manual_review', true)
                    ->count(),
                'amount_mismatch' => $this->countAmountMismatch(),
                'repaired_today' => $this->repairedTodayCount(),
            ],
            'queues' => [
                'payment_exceptions' => $this->paymentExceptionItems(),
                'fulfillment_exceptions' => $this->fulfillmentExceptionItems(),
                'provider_uncertainty' => $this->providerUncertaintyItems(),
                'manual_review' => $this->manualReviewItems(),
                'dead_letters' => $this->deadLetterItems(),
            ],
            'config' => [
                'payment_pending_stale_minutes' => $paymentStale,
                'fulfillment_processing_stale_minutes' => $fulfillmentStale,
                'fulfillment_uncertain_escalation_minutes' => max(
                    15,
                    $this->systemSettings->getInt(
                        SystemSettingKeys::FULFILLMENT_UNCERTAIN_ESCALATION_MINUTES,
                        120,
                    ),
                ),
                'reconciliation_batch_size' => max(
                    1,
                    $this->systemSettings->getInt(SystemSettingKeys::RECONCILIATION_BATCH_SIZE, 50),
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcilePayment(string $reference): array
    {
        $summary = $this->paymentReconciliationService->reconcile(
            reference: $reference,
            repair: true,
        );

        return [
            'reference' => $reference,
            'summary' => $summary,
            'transaction' => $this->itemForReference($reference),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reconcileFulfillment(string $reference): array
    {
        $summary = $this->fulfillmentReconciliationService->reconcile(
            reference: $reference,
            repair: true,
        );

        return [
            'reference' => $reference,
            'summary' => $summary,
            'transaction' => $this->itemForReference($reference),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function retryConfirmedFailure(string $reference): array
    {
        $transaction = Transaction::query()->where('reference', $reference)->firstOrFail();

        $this->transactionEventService->record(
            $transaction,
            TransactionEventService::TYPE_OPERATOR_RETRY_REQUESTED,
            'Operator requested fulfillment retry.',
            'operator',
        );

        $result = $this->exactOnceFulfillmentService->requestFromManualRetry($transaction);

        return [
            'outcome' => $result->outcome,
            'reason' => $result->reason,
            'transaction' => $this->itemForReference($reference),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function moveToManualReview(string $reference, string $reason): array
    {
        $transaction = Transaction::query()->where('reference', $reference)->firstOrFail();

        $transaction->update([
            'needs_manual_review' => true,
            'manual_review_reason' => $reason,
            'manual_review_at' => now(),
            'next_fulfillment_retry_at' => null,
        ]);

        $this->transactionEventService->record(
            $transaction->fresh(),
            TransactionEventService::TYPE_MANUAL_REVIEW_OPENED,
            $reason,
            'operator',
        );

        return ['transaction' => $this->itemForReference($reference)];
    }

    /**
     * @return array<string, mixed>
     */
    public function resumeAutomation(string $reference): array
    {
        $transaction = Transaction::query()->where('reference', $reference)->firstOrFail();

        $transaction->update([
            'needs_manual_review' => false,
            'manual_review_reason' => null,
            'manual_review_at' => null,
        ]);

        $this->transactionEventService->record(
            $transaction->fresh(),
            TransactionEventService::TYPE_MANUAL_REVIEW_RESOLVED,
            'Operator resumed automated fulfillment.',
            'operator',
        );

        return ['transaction' => $this->itemForReference($reference)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentExceptionItems(int $limit = 20): array
    {
        $cutoff = now()->subMinutes($this->paymentStaleMinutes());

        return Transaction::query()
            ->where('status', TransactionStatus::PAYMENT_PENDING)
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction) => $this->toQueueItem($transaction, 'stale_payment_pending'))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fulfillmentExceptionItems(int $limit = 20): array
    {
        $cutoff = now()->subMinutes($this->paymentStaleMinutes());

        return Transaction::query()
            ->where('status', TransactionStatus::PAYMENT_SUCCESS)
            ->where('needs_manual_review', false)
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction) => $this->toQueueItem($transaction, 'paid_unfulfilled'))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function providerUncertaintyItems(int $limit = 20): array
    {
        return FulfillmentAttempt::query()
            ->with('transaction')
            ->whereIn('status', [FulfillmentAttemptStatus::UNCERTAIN, FulfillmentAttemptStatus::SUBMITTED])
            ->orderBy('started_at')
            ->limit($limit)
            ->get()
            ->map(function (FulfillmentAttempt $attempt) {
                $transaction = $attempt->transaction;

                return $transaction
                    ? $this->toQueueItem($transaction, 'fulfillment_uncertain', $attempt)
                    : [];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function manualReviewItems(int $limit = 20): array
    {
        return Transaction::query()
            ->where('needs_manual_review', true)
            ->orderByDesc('manual_review_at')
            ->limit($limit)
            ->get()
            ->map(fn (Transaction $transaction) => $this->toQueueItem(
                $transaction,
                'manual_review_required',
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function deadLetterItems(int $limit = 20): array
    {
        return FulfillmentAttempt::query()
            ->with('transaction')
            ->where('status', FulfillmentAttemptStatus::DEAD_LETTER)
            ->orderByDesc('resolved_at')
            ->limit($limit)
            ->get()
            ->map(function (FulfillmentAttempt $attempt) {
                $transaction = $attempt->transaction;

                return $transaction
                    ? $this->toQueueItem($transaction, 'retry_exhausted', $attempt)
                    : [];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function itemForReference(string $reference): ?array
    {
        $transaction = Transaction::query()->where('reference', $reference)->first();

        return $transaction ? $this->toQueueItem($transaction) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function toQueueItem(
        Transaction $transaction,
        ?string $classification = null,
        ?FulfillmentAttempt $attempt = null,
    ): array {
        $latestAttempt = $attempt ?? $transaction->fulfillmentAttempts()->orderByDesc('id')->first();

        return [
            'reference' => $transaction->reference,
            'classification' => $classification,
            'customer_phone' => $transaction->customer_phone,
            'customer_email' => $transaction->customer_email,
            'product_type' => $transaction->product_type,
            'amount' => $transaction->payable_amount,
            'payment_state' => $transaction->status,
            'fulfillment_state' => $transaction->status,
            'payment_reference' => $transaction->payment_reference,
            'vtpass_request_id' => $latestAttempt?->request_id,
            'provider_response' => $latestAttempt?->provider_message ?? $latestAttempt?->failure_reason,
            'age_minutes' => $transaction->updated_at
                ? (int) $transaction->updated_at->diffInMinutes(now())
                : null,
            'retry_count' => $transaction->fulfillment_retry_count,
            'next_retry_at' => $transaction->next_fulfillment_retry_at?->toIso8601String(),
            'manual_review_reason' => $transaction->manual_review_reason,
            'needs_manual_review' => $transaction->needs_manual_review,
        ];
    }

    private function paymentStaleMinutes(): int
    {
        return max(
            5,
            $this->systemSettings->getInt(
                SystemSettingKeys::PAYMENT_PENDING_STALE_MINUTES,
                $this->systemSettings->getInt(SystemSettingKeys::PAYMENT_RECONCILE_STALE_MINUTES, 15),
            ),
        );
    }

    private function fulfillmentStaleMinutes(): int
    {
        return max(
            5,
            $this->systemSettings->getInt(
                SystemSettingKeys::FULFILLMENT_PROCESSING_STALE_MINUTES,
                $this->systemSettings->getInt(SystemSettingKeys::FULFILLMENT_STALE_MINUTES, 30),
            ),
        );
    }

    private function countPaidUnfulfilled(int $paymentStale): int
    {
        return (int) Transaction::query()
            ->where('status', TransactionStatus::PAYMENT_SUCCESS)
            ->where('needs_manual_review', false)
            ->where('updated_at', '<=', now()->subMinutes($paymentStale))
            ->count();
    }

    private function countAmountMismatch(): int
    {
        return (int) Transaction::query()
            ->where('needs_manual_review', true)
            ->where('manual_review_reason', 'like', '%amount%')
            ->count();
    }

    private function repairedTodayCount(): int
    {
        return (int) TransactionEvent::query()
            ->whereIn('event_type', [
                TransactionEventService::TYPE_PAYMENT_RECONCILIATION_REPAIRED,
                TransactionEventService::TYPE_FULFILLMENT_RECONCILIATION_REPAIRED,
                TransactionEventService::TYPE_RECONCILIATION_REPAIRED,
            ])
            ->where('occurred_at', '>=', Carbon::today())
            ->count();
    }
}
