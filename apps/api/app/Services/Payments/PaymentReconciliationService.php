<?php

namespace App\Services\Payments;

use App\Enums\TransactionStatus;
use App\Exceptions\PaymentVerificationException;
use App\Exceptions\PaystackException;
use App\Models\Transaction;
use App\Services\Fulfillment\FulfillmentRetryService;
use App\Services\TransactionEventService;
use App\Services\WebhookEventService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentReconciliationService
{
    public function __construct(
        private readonly PaymentVerificationService $paymentVerificationService,
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
     *     errors: int
     * }
     */
    public function reconcile(
        int $paymentStaleMinutes = 15,
        int $fulfillmentStaleMinutes = 30,
    ): array {
        $summary = [
            'payments_checked' => 0,
            'payments_repaired' => 0,
            'fulfillments_checked' => 0,
            'fulfillments_repaired' => 0,
            'webhooks_retried' => 0,
            'errors' => 0,
        ];

        $paymentCutoff = now()->subMinutes($paymentStaleMinutes);
        $fulfillmentCutoff = now()->subMinutes($fulfillmentStaleMinutes);

        Transaction::query()
            ->where('status', TransactionStatus::PAYMENT_PENDING)
            ->where('updated_at', '<=', $paymentCutoff)
            ->orderBy('id')
            ->chunkById(50, function ($transactions) use (&$summary) {
                foreach ($transactions as $transaction) {
                    $summary['payments_checked']++;
                    $this->reconcilePayment($transaction, $summary);
                }
            });

        Transaction::query()
            ->where('status', TransactionStatus::PAYMENT_SUCCESS)
            ->where('needs_manual_review', false)
            ->where('updated_at', '<=', $paymentCutoff)
            ->orderBy('id')
            ->chunkById(50, function ($transactions) use (&$summary) {
                foreach ($transactions as $transaction) {
                    $summary['fulfillments_checked']++;
                    $this->reconcileUnfulfilledPayment($transaction, $summary);
                }
            });

        Transaction::query()
            ->where('status', TransactionStatus::FULFILLMENT_PENDING)
            ->where('updated_at', '<=', $fulfillmentCutoff)
            ->orderBy('id')
            ->chunkById(50, function ($transactions) use (&$summary) {
                foreach ($transactions as $transaction) {
                    $summary['fulfillments_checked']++;
                    $this->reconcileStaleFulfillment($transaction, $summary);
                }
            });

        foreach ($this->webhookEventService->failedEventsForRetry() as $event) {
            $summary['webhooks_retried']++;
            $this->retryFailedWebhook($event, $summary);
        }

        return $summary;
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
                $this->recordRepair($fresh, 'Payment state repaired during reconciliation.');
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

        if (! $transaction->fulfillmentAttempts()->exists()
            && ! data_get($transaction->response_payload, 'auto_fulfill.attempted')
            && $transaction->fulfillment_retry_count === 0) {
            return;
        }

        if ($this->fulfillmentRetryService->scheduleOrProcess($transaction)) {
            $summary['fulfillments_repaired']++;
            $this->recordRepair(
                $transaction->fresh(),
                'Unfulfilled payment queued for automated fulfillment retry.',
            );
        }
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function reconcileStaleFulfillment(Transaction $transaction, array &$summary): void
    {
        if ($this->fulfillmentRetryService->scheduleOrProcess($transaction->fresh())) {
            $summary['fulfillments_repaired']++;
            $this->recordRepair(
                $transaction->fresh(),
                'Stale fulfillment pending state repaired during reconciliation.',
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

    private function recordRepair(Transaction $transaction, string $summary): void
    {
        $this->transactionEventService->record(
            $transaction,
            TransactionEventService::TYPE_RECONCILIATION_REPAIRED,
            $summary,
            'reconciliation',
        );
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
            'stale_payment_success' => (int) Transaction::query()
                ->where('status', TransactionStatus::PAYMENT_SUCCESS)
                ->where('needs_manual_review', false)
                ->where('updated_at', '<=', $paymentCutoff)
                ->count(),
            'stale_fulfillment_pending' => (int) Transaction::query()
                ->where('status', TransactionStatus::FULFILLMENT_PENDING)
                ->where('updated_at', '<=', $fulfillmentCutoff)
                ->count(),
        ];
    }
}
