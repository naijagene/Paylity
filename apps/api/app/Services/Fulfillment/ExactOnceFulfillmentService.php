<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentAttemptStatus;
use App\Enums\FulfillmentTriggerSource;
use App\Enums\TransactionStatus;
use App\Exceptions\FulfillmentException;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;
use App\Services\TransactionEventService;
use App\Support\Fulfillment\FulfillmentOrchestrationResult;
use Illuminate\Support\Facades\DB;

class ExactOnceFulfillmentService
{
    public function __construct(
        private readonly FulfillmentService $fulfillmentService,
        private readonly FulfillmentAttemptRecorder $attemptRecorder,
        private readonly TransactionEventService $transactionEventService,
        private readonly VtpassFulfillmentGuard $vtpassFulfillmentGuard,
    ) {
    }

    public function requestFulfillment(
        Transaction $transaction,
        string $triggerSource,
        string $actor = 'system',
        ?string $operatorId = null,
        bool $isRetry = false,
    ): FulfillmentOrchestrationResult {
        if (! $this->fulfillmentService->isEnabled()) {
            return $this->ignored(
                $transaction,
                'ignored',
                'VTPass fulfillment is disabled.',
            );
        }

        $reservation = DB::transaction(function () use (
            $transaction,
            $triggerSource,
            $actor,
            $operatorId,
            $isRetry,
        ) {
            $locked = Transaction::query()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new FulfillmentException('Transaction not found.', 'TRANSACTION_NOT_FOUND');
            }

            if ($locked->status === TransactionStatus::FULFILLED) {
                return $this->reservationResult($locked, 'already_fulfilled', 'Transaction already fulfilled.');
            }

            if ($locked->status === TransactionStatus::CANCELLED) {
                return $this->reservationResult($locked, 'ignored', 'Cancelled transactions cannot be fulfilled.');
            }

            if ($locked->needs_manual_review) {
                return $this->reservationResult($locked, 'manual_review', 'Transaction requires manual review.');
            }

            if (! $this->paymentConfirmed($locked)) {
                return $this->reservationResult(
                    $locked,
                    'payment_not_confirmed',
                    'Payment must be confirmed before fulfillment.',
                );
            }

            if ($locked->status === TransactionStatus::PAYMENT_FAILED) {
                return $this->reservationResult(
                    $locked,
                    'ignored',
                    'Payment failed transactions cannot be fulfilled.',
                );
            }

            if ($this->attemptRecorder->hasSuccessfulAttempt($locked)) {
                return $this->reservationResult(
                    $locked,
                    'already_fulfilled',
                    'A successful fulfillment attempt already exists.',
                );
            }

            $uncertainAttempt = $this->findBlockingAttempt($locked);
            if ($uncertainAttempt !== null) {
                return $this->reservationResult(
                    $locked,
                    'active_attempt',
                    'An uncertain or in-flight fulfillment attempt must be reconciled first.',
                    $uncertainAttempt,
                );
            }

            if ($this->attemptRecorder->hasActiveAttempt($locked)) {
                return $this->reservationResult(
                    $locked,
                    'active_attempt',
                    'Another fulfillment attempt is already processing.',
                );
            }

            try {
                $this->vtpassFulfillmentGuard->assertCanFulfill($locked);
            } catch (FulfillmentException $exception) {
                throw $exception;
            }

            $attemptNumber = $this->attemptRecorder->nextAttemptNumber($locked);
            $attempt = $this->attemptRecorder->createPending(
                $locked,
                $triggerSource,
                $actor,
                $attemptNumber,
                $operatorId,
            );

            $locked->update([
                'status' => TransactionStatus::FULFILLMENT_PENDING,
                'fulfillment_provider' => 'vtpass',
                'failure_reason' => null,
            ]);

            $this->transactionEventService->record(
                $locked->fresh(),
                TransactionEventService::TYPE_FULFILLMENT_ATTEMPT_CREATED,
                'Fulfillment attempt reserved.',
                $actor,
                [
                    'attempt_number' => $attemptNumber,
                    'trigger_source' => $triggerSource,
                    'request_id' => $attempt->request_id,
                ],
            );

            return [
                'outcome' => 'execute',
                'transaction' => $locked->fresh(),
                'attempt' => $attempt,
                'is_retry' => $isRetry,
                'actor' => $actor,
            ];
        });

        if (! is_array($reservation) || ($reservation['outcome'] ?? '') !== 'execute') {
            return new FulfillmentOrchestrationResult(
                outcome: (string) ($reservation['outcome'] ?? 'ignored'),
                transaction: $reservation['transaction'],
                reason: $reservation['reason'] ?? null,
                attempt: $reservation['attempt'] ?? null,
            );
        }

        try {
            $fulfilled = $this->fulfillmentService->executeAttempt(
                $reservation['transaction'],
                $reservation['attempt'],
                $reservation['actor'],
                (bool) $reservation['is_retry'],
            );

            return new FulfillmentOrchestrationResult(
                outcome: 'fulfilled',
                transaction: $fulfilled,
                attempt: $reservation['attempt']->fresh(),
            );
        } catch (FulfillmentException $exception) {
            if (in_array($exception->errorCode, [
                'VTPASS_LIVE_SAFETY_LIMIT',
                'VTPASS_PRODUCT_NOT_READY',
                'SERVICE_DISABLED',
                'UNSUPPORTED_PRODUCT_TYPE',
                'UNSUPPORTED_NETWORK',
            ], true)) {
                throw $exception;
            }

            return new FulfillmentOrchestrationResult(
                outcome: $exception->errorCode === 'FULFILLMENT_UNCERTAIN' ? 'uncertain' : 'failed',
                transaction: $reservation['transaction']->fresh(),
                reason: $exception->getMessage(),
                attempt: $reservation['attempt']->fresh(),
            );
        }
    }

    public function requestFromWebhook(Transaction $transaction): FulfillmentOrchestrationResult
    {
        return $this->requestFulfillment(
            $transaction,
            FulfillmentTriggerSource::WEBHOOK,
            'webhook',
        );
    }

    public function requestFromCallback(Transaction $transaction): FulfillmentOrchestrationResult
    {
        return $this->requestFulfillment(
            $transaction,
            FulfillmentTriggerSource::CALLBACK,
            'callback',
        );
    }

    public function requestFromReconciliation(Transaction $transaction, bool $isRetry = false): FulfillmentOrchestrationResult
    {
        return $this->requestFulfillment(
            $transaction,
            FulfillmentTriggerSource::RECONCILIATION,
            'reconciliation',
            isRetry: $isRetry,
        );
    }

    public function requestFromAutomaticRetry(Transaction $transaction): FulfillmentOrchestrationResult
    {
        return $this->requestFulfillment(
            $transaction,
            FulfillmentTriggerSource::AUTOMATIC_RETRY,
            'retry_engine',
            isRetry: true,
        );
    }

    public function requestFromManualRetry(
        Transaction $transaction,
        ?string $operatorId = null,
    ): FulfillmentOrchestrationResult {
        return $this->requestFulfillment(
            $transaction,
            FulfillmentTriggerSource::MANUAL_RETRY,
            'operator',
            $operatorId,
            true,
        );
    }

    public function requestFromOperator(
        Transaction $transaction,
        ?string $operatorId = null,
    ): FulfillmentOrchestrationResult {
        return $this->requestFulfillment(
            $transaction,
            FulfillmentTriggerSource::OPERATOR,
            'operator',
            $operatorId,
        );
    }

    private function paymentConfirmed(Transaction $transaction): bool
    {
        return in_array($transaction->status, [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FAILED,
            TransactionStatus::FULFILLMENT_PENDING,
        ], true);
    }

    private function findBlockingAttempt(Transaction $transaction): ?FulfillmentAttempt
    {
        return FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->whereIn('status', [
                FulfillmentAttemptStatus::UNCERTAIN,
                FulfillmentAttemptStatus::SUBMITTED,
            ])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationResult(
        Transaction $transaction,
        string $outcome,
        string $reason,
        ?FulfillmentAttempt $attempt = null,
    ): array {
        $this->transactionEventService->record(
            $transaction,
            TransactionEventService::TYPE_FULFILLMENT_TRIGGER_IGNORED,
            $reason,
            'orchestrator',
            ['outcome' => $outcome],
        );

        return [
            'outcome' => $outcome,
            'transaction' => $transaction,
            'reason' => $reason,
            'attempt' => $attempt,
        ];
    }

    private function ignored(
        Transaction $transaction,
        string $outcome,
        string $reason,
    ): FulfillmentOrchestrationResult {
        return new FulfillmentOrchestrationResult(
            outcome: $outcome,
            transaction: $transaction,
            reason: $reason,
        );
    }
}
