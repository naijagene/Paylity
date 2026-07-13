<?php

namespace App\Services\Payments;

use App\Enums\FulfillmentAttemptStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\PaymentVerificationException;
use App\Exceptions\PaystackException;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;
use App\Services\Fulfillment\ExactOnceFulfillmentService;
use App\Services\Fulfillment\FulfillmentRetryService;
use App\Services\TransactionEventService;
use App\Services\WebhookEventService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentVerificationService $paymentVerificationService,
        private readonly ExactOnceFulfillmentService $exactOnceFulfillmentService,
        private readonly FulfillmentRetryService $fulfillmentRetryService,
        private readonly TransactionEventService $transactionEventService,
        private readonly WebhookEventService $webhookEventService,
    ) {
    }

    /**
     * @return array{
     *     payments_checked: int,
     *     payments_repaired: int,
     *     fulfillments_checked: int,
     *     fulfillments_repaired: int,
     *     webhooks_retried: int,
     *     escalated: int,
     *     errors: int
     * }
     */
    public function reconcile(
        int $paymentStaleMinutes = 15,
        int $fulfillmentStaleMinutes = 30,
        ?string $reference = null,
        ?Carbon $since = null,
        int $limit = 50,
        bool $dryRun = false,
        bool $repair = true,
    ): array {
        $summary = [
            'payments_checked' => 0,
            'payments_repaired' => 0,
            'fulfillments_checked' => 0,
            'fulfillments_repaired' => 0,
            'webhooks_retried' => 0,
            'escalated' => 0,
            'errors' => 0,
        ];

        if ($reference !== null && $reference !== '') {
            $transaction = Transaction::query()->where('reference', $reference)->first();
            if ($transaction) {
                $summary['payments_checked']++;
                if (! $dryRun && $repair) {
                    $this->reconcileTransaction($transaction, $summary, $paymentStaleMinutes, $fulfillmentStaleMinutes);
                }
            }

            return $summary;
        }

        $paymentCutoff = now()->subMinutes($paymentStaleMinutes);
        $fulfillmentCutoff = now()->subMinutes($fulfillmentStaleMinutes);

        if ($since !== null) {
            $paymentCutoff = $since;
            $fulfillmentCutoff = $since;
        }

        $this->eachCandidate(
            Transaction::query()
                ->whereIn('status', [
                    TransactionStatus::PAYMENT_PENDING,
                    TransactionStatus::PAYMENT_SUCCESS,
                    TransactionStatus::FULFILLMENT_PENDING,
                    TransactionStatus::FAILED,
                ])
                ->when($since, fn ($query) => $query->where('created_at', '>=', $since))
                ->orderBy('id')
                ->limit($limit),
            function (Transaction $transaction) use (
                &$summary,
                $paymentStaleMinutes,
                $paymentCutoff,
                $fulfillmentCutoff,
                $fulfillmentStaleMinutes,
                $dryRun,
                $repair,
            ) {
                if ($transaction->status === TransactionStatus::PAYMENT_PENDING
                    && $transaction->updated_at->gt($paymentCutoff)) {
                    return;
                }

                if (in_array($transaction->status, [
                    TransactionStatus::PAYMENT_SUCCESS,
                    TransactionStatus::FAILED,
                ], true) && $transaction->updated_at->gt($paymentCutoff)) {
                    return;
                }

                if ($transaction->status === TransactionStatus::FULFILLMENT_PENDING
                    && $transaction->updated_at->gt($fulfillmentCutoff)) {
                    return;
                }

                $summary['payments_checked']++;

                if ($dryRun || ! $repair) {
                    return;
                }

                $this->reconcileTransaction(
                    $transaction,
                    $summary,
                    $paymentStaleMinutes,
                    $fulfillmentStaleMinutes,
                );
            },
        );

        if (! $dryRun && $repair) {
            foreach ($this->webhookEventService->failedEventsForRetry() as $event) {
                if ($summary['webhooks_retried'] >= $limit) {
                    break;
                }

                $summary['webhooks_retried']++;
                $this->retryFailedWebhook($event, $summary);
            }
        }

        $this->auditLedgerMismatches($summary, $limit, $dryRun, $repair);

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function reconcileTransaction(
        Transaction $transaction,
        array &$summary,
        int $paymentStaleMinutes,
        int $fulfillmentStaleMinutes,
    ): void {
        $this->transactionEventService->record(
            $transaction,
            TransactionEventService::TYPE_PAYMENT_RECONCILIATION_STARTED,
            'Payment reconciliation started.',
            'reconciliation',
        );

        if (in_array($transaction->status, [
            TransactionStatus::PAYMENT_PENDING,
            TransactionStatus::CREATED,
        ], true)) {
            $this->reconcilePayment($transaction, $summary);

            return;
        }

        if ($transaction->status === TransactionStatus::PAYMENT_SUCCESS) {
            $summary['fulfillments_checked']++;
            $this->reconcileUnfulfilledPayment($transaction, $summary);

            return;
        }

        if ($transaction->status === TransactionStatus::FULFILLMENT_PENDING) {
            $summary['fulfillments_checked']++;
            $this->reconcileStaleFulfillment($transaction, $summary);
        }

        if ($transaction->status === TransactionStatus::FAILED
            && $transaction->payment_reference
            && data_get($transaction->response_payload, 'verify.status') === 'success') {
            $this->escalateMismatch($transaction, 'Failed transaction has verified Paystack success.', $summary);
        }
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function reconcilePayment(Transaction $transaction, array &$summary): void
    {
        if (! $this->paymentVerificationService->isConfigured()) {
            return;
        }

        $previousStatus = $transaction->status;

        try {
            $result = $this->paymentVerificationService->verify($transaction->reference);
            $fresh = $result['transaction']->fresh();

            if ($fresh && $fresh->status !== $previousStatus) {
                $summary['payments_repaired']++;
                $this->recordRepair(
                    $fresh,
                    'Payment state repaired during reconciliation.',
                    TransactionEventService::TYPE_PAYMENT_RECONCILIATION_REPAIRED,
                );
            }
        } catch (PaymentVerificationException|PaystackException $exception) {
            $summary['errors']++;
            Log::warning('Payment reconciliation failed.', [
                'reference' => $transaction->reference,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function reconcileUnfulfilledPayment(Transaction $transaction, array &$summary): void
    {
        if ($transaction->status !== TransactionStatus::PAYMENT_SUCCESS) {
            return;
        }

        if ($transaction->needs_manual_review) {
            return;
        }

        $hasSuccessfulAttempt = FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->where('status', FulfillmentAttemptStatus::SUCCEEDED)
            ->exists();

        if ($hasSuccessfulAttempt && $transaction->status !== TransactionStatus::FULFILLED) {
            $this->escalateMismatch($transaction, 'Successful attempt exists without fulfilled status.', $summary);

            return;
        }

        $result = $this->exactOnceFulfillmentService->requestFromReconciliation($transaction);

        if ($result->fulfilled()) {
            $summary['fulfillments_repaired']++;
            $this->recordRepair(
                $result->transaction,
                'Paid transaction fulfilled during reconciliation.',
                TransactionEventService::TYPE_FULFILLMENT_RECONCILIATION_REPAIRED,
            );

            return;
        }

        if ($result->outcome === 'uncertain' || $result->outcome === 'active_attempt') {
            return;
        }

        if ($this->fulfillmentRetryService->scheduleOrProcess($transaction)) {
            $summary['fulfillments_repaired']++;
            $this->recordRepair(
                $transaction->fresh(),
                'Unfulfilled payment queued for automated fulfillment retry.',
                TransactionEventService::TYPE_FULFILLMENT_RECONCILIATION_REPAIRED,
            );
        }
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function reconcileStaleFulfillment(Transaction $transaction, array &$summary): void
    {
        $uncertain = FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->whereIn('status', [FulfillmentAttemptStatus::UNCERTAIN, FulfillmentAttemptStatus::SUBMITTED])
            ->exists();

        if ($uncertain) {
            return;
        }

        if ($this->fulfillmentRetryService->scheduleOrProcess($transaction->fresh())) {
            $summary['fulfillments_repaired']++;
            $this->recordRepair(
                $transaction->fresh(),
                'Stale fulfillment pending state repaired during reconciliation.',
                TransactionEventService::TYPE_FULFILLMENT_RECONCILIATION_REPAIRED,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, int>  $summary
     */
    private function retryFailedWebhook(array $event, array &$summary): void
    {
        $reference = (string) ($event['reference'] ?? '');

        if ($reference === '' || ! $this->paymentVerificationService->isConfigured()) {
            return;
        }

        try {
            $result = $this->paymentVerificationService->verify($reference);
            $this->webhookEventService->markProcessed(
                (int) $event['id'],
                $result['transaction']->status,
            );
            $summary['payments_repaired']++;
        } catch (PaymentVerificationException|PaystackException $exception) {
            $summary['errors']++;
            $this->webhookEventService->markFailed((int) $event['id'], $exception->getMessage());
        }
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function auditLedgerMismatches(
        array &$summary,
        int $limit,
        bool $dryRun,
        bool $repair,
    ): void {
        Transaction::query()
            ->where('status', TransactionStatus::FULFILLED)
            ->whereDoesntHave('fulfillmentAttempts', function ($query) {
                $query->where('status', FulfillmentAttemptStatus::SUCCEEDED);
            })
            ->limit($limit)
            ->get()
            ->each(function (Transaction $transaction) use (&$summary, $dryRun, $repair) {
                $summary['fulfillments_checked']++;

                if ($dryRun || ! $repair) {
                    return;
                }

                $this->escalateMismatch(
                    $transaction,
                    'Fulfilled transaction missing successful fulfillment attempt ledger entry.',
                    $summary,
                );
            });
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function escalateMismatch(Transaction $transaction, string $reason, array &$summary): void
    {
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
            'reconciliation',
        );

        $summary['escalated']++;
    }

    private function recordRepair(
        Transaction $transaction,
        string $summary,
        string $eventType = TransactionEventService::TYPE_RECONCILIATION_REPAIRED,
    ): void {
        $this->transactionEventService->record(
            $transaction,
            $eventType,
            $summary,
            'reconciliation',
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Transaction>  $query
     */
    private function eachCandidate($query, callable $callback): void
    {
        foreach ($query->get() as $transaction) {
            $callback($transaction);
        }
    }

    /**
     * @return array<string, int>
     */
    public function staleCounts(int $paymentStaleMinutes = 15, int $fulfillmentStaleMinutes = 30): array
    {
        $paymentCutoff = Carbon::now()->subMinutes($paymentStaleMinutes);
        $fulfillmentCutoff = Carbon::now()->subMinutes($fulfillmentStaleMinutes);

        return [
            'stale_payment_pending' => (int) Transaction::query()
                ->where('status', TransactionStatus::PAYMENT_PENDING)
                ->where('updated_at', '<=', $paymentCutoff)
                ->count(),
            'paid_unfulfilled' => (int) Transaction::query()
                ->where('status', TransactionStatus::PAYMENT_SUCCESS)
                ->where('needs_manual_review', false)
                ->where('updated_at', '<=', $paymentCutoff)
                ->count(),
            'stale_fulfillment_pending' => (int) Transaction::query()
                ->where('status', TransactionStatus::FULFILLMENT_PENDING)
                ->where('updated_at', '<=', $fulfillmentCutoff)
                ->count(),
            'manual_review' => (int) Transaction::query()
                ->where('needs_manual_review', true)
                ->count(),
        ];
    }
}
