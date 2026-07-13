<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentAttemptStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\FulfillmentException;
use App\Exceptions\VTPassException;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;
use App\Services\Finance\LedgerPostingService;
use App\Services\Fulfillment\Adapters\AirtimeAdapter;
use App\Services\Fulfillment\Adapters\DataAdapter;
use App\Services\Fulfillment\Adapters\ElectricityAdapter;
use App\Services\Fulfillment\Adapters\FulfillmentAdapterInterface;
use App\Services\Notifications\TransactionNotificationService;
use App\Services\TransactionEventService;

class FulfillmentService
{
    /** @var array<int, FulfillmentAdapterInterface> */
    private array $adapters;

    public function __construct(
        private readonly VTPassService $vtpassService,
        private readonly VTPassResponseMapper $responseMapper,
        private readonly FulfillmentAttemptRecorder $fulfillmentAttemptRecorder,
        private readonly TransactionEventService $transactionEventService,
        private readonly TransactionNotificationService $transactionNotificationService,
        private readonly VtpassFulfillmentGuard $vtpassFulfillmentGuard,
        private readonly LedgerPostingService $ledgerPostingService,
        AirtimeAdapter $airtimeAdapter,
        DataAdapter $dataAdapter,
        ElectricityAdapter $electricityAdapter,
    ) {
        $this->adapters = [$airtimeAdapter, $dataAdapter, $electricityAdapter];
    }

    public function isEnabled(): bool
    {
        return $this->vtpassService->isEnabled();
    }

    /**
     * @return array<string, mixed>
     */
    public function toResponse(Transaction $transaction): array
    {
        return [
            'reference' => $transaction->reference,
            'status' => $transaction->status,
            'product_type' => $transaction->product_type,
            'fulfillment_provider' => $transaction->fulfillment_provider,
            'fulfillment_reference' => $transaction->fulfillment_reference,
            'fulfillment_status' => $this->fulfillmentStatus($transaction),
            'failure_reason' => $transaction->failure_reason,
        ];
    }

    /**
     * @throws FulfillmentException
     * @throws VTPassException
     */
    public function fulfillByReference(string $reference): Transaction
    {
        $transaction = Transaction::query()
            ->where('reference', $reference)
            ->first();

        if (! $transaction) {
            throw new FulfillmentException(
                'Transaction not found.',
                'TRANSACTION_NOT_FOUND',
            );
        }

        throw new FulfillmentException(
            'Direct fulfillment is disabled. Use ExactOnceFulfillmentService.',
            'FULFILLMENT_ORCHESTRATION_REQUIRED',
        );
    }

    /**
     * @throws FulfillmentException
     * @throws VTPassException
     *
     * @deprecated Use ExactOnceFulfillmentService::requestFulfillment()
     */
    public function fulfill(Transaction $transaction, string $actor = 'system', bool $isRetry = false): Transaction
    {
        throw new FulfillmentException(
            'Direct fulfillment is disabled. Use ExactOnceFulfillmentService.',
            'FULFILLMENT_ORCHESTRATION_REQUIRED',
        );
    }

    /**
     * @throws FulfillmentException
     * @throws VTPassException
     */
    public function executeAttempt(
        Transaction $transaction,
        FulfillmentAttempt $attempt,
        string $actor = 'system',
        bool $isRetry = false,
    ): Transaction {
        $this->vtpassFulfillmentGuard->assertCanFulfill($transaction);
        $this->vtpassService->assertConfigured();

        $adapter = $this->resolveAdapter($transaction->product_type);
        $payload = $adapter->buildPayload($transaction);
        $payload['request_id'] = (string) $attempt->request_id;

        $this->fulfillmentAttemptRecorder->markSubmitted($attempt, $payload);

        $this->transactionEventService->record(
            $transaction,
            TransactionEventService::TYPE_FULFILLMENT_REQUEST_SUBMITTED,
            'Fulfillment request submitted to provider.',
            $actor,
            ['request_id' => $attempt->request_id],
        );

        $startedAt = microtime(true);

        try {
            $response = $this->vtpassService->pay($payload);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $mapped = $this->responseMapper->map($response);

            if ($mapped['status'] === VTPassResponseMapper::STATUS_SUCCESS) {
                return $this->markFulfilled(
                    $transaction,
                    $attempt,
                    $response,
                    $payload,
                    $actor,
                    $isRetry,
                    $durationMs,
                    $mapped,
                );
            }

            if (in_array($mapped['status'], [
                VTPassResponseMapper::STATUS_PENDING,
                VTPassResponseMapper::STATUS_UNKNOWN,
            ], true)) {
                return $this->markUncertainAndThrow(
                    $transaction,
                    $attempt,
                    $payload,
                    $response,
                    $mapped['message'],
                    $durationMs,
                    $mapped['code'],
                );
            }

            return $this->markFailedAndThrow(
                $transaction,
                $attempt,
                $response,
                $payload,
                $mapped['message'],
                $actor,
                $durationMs,
                $mapped['code'],
            );
        } catch (VTPassException $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($exception->errorCode === 'VTPASS_TIMEOUT') {
                $this->fulfillmentAttemptRecorder->markUncertain(
                    $attempt,
                    $payload,
                    null,
                    $exception->getMessage(),
                    $durationMs,
                    VTPassException::class,
                    $exception->errorCode,
                );

                $transaction->update([
                    'status' => TransactionStatus::FULFILLMENT_PENDING,
                    'failure_reason' => $exception->getMessage(),
                ]);

                $this->transactionEventService->record(
                    $transaction->fresh(),
                    TransactionEventService::TYPE_FULFILLMENT_PROVIDER_UNCERTAIN,
                    'Fulfillment provider outcome uncertain after timeout.',
                    $actor,
                    ['request_id' => $attempt->request_id],
                );

                throw new FulfillmentException(
                    $exception->getMessage(),
                    'FULFILLMENT_UNCERTAIN',
                );
            }

            $this->fulfillmentAttemptRecorder->markConfirmedFailed(
                $attempt,
                null,
                $exception->errorCode,
                $exception->getMessage(),
                $exception->getMessage(),
                $durationMs,
                VTPassException::class,
                $exception->errorCode,
            );

            $transaction->update([
                'status' => TransactionStatus::FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);

            $this->transactionEventService->record(
                $transaction->fresh(),
                TransactionEventService::TYPE_FULFILLMENT_PROVIDER_FAILED,
                'Fulfillment provider error.',
                $actor,
                ['reason' => $exception->getMessage()],
            );

            $this->transactionNotificationService->sendDeliveryFailure($transaction->fresh());

            throw $exception;
        }
    }

    /**
     * @throws FulfillmentException
     */
    public function retryFulfillment(Transaction $transaction, string $actor = 'operator'): Transaction
    {
        throw new FulfillmentException(
            'Direct fulfillment retry is disabled. Use ExactOnceFulfillmentService.',
            'FULFILLMENT_ORCHESTRATION_REQUIRED',
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $mapped
     */
    private function markFulfilled(
        Transaction $transaction,
        FulfillmentAttempt $attempt,
        array $response,
        array $payload,
        string $actor,
        bool $isRetry,
        int $durationMs,
        array $mapped,
    ): Transaction {
        $providerReference = $this->resolveFulfillmentReference($response, $payload);

        $transaction->update([
            'status' => TransactionStatus::FULFILLED,
            'fulfillment_provider' => 'vtpass',
            'fulfillment_reference' => $providerReference,
            'response_payload' => array_merge(
                (array) $transaction->response_payload,
                ['fulfillment' => $response],
            ),
            'failure_reason' => null,
            'fulfilled_at' => now(),
            'fulfillment_retry_count' => 0,
            'next_fulfillment_retry_at' => null,
            'needs_manual_review' => false,
            'manual_review_reason' => null,
            'manual_review_at' => null,
        ]);

        $this->fulfillmentAttemptRecorder->markSucceeded(
            $attempt,
            $response,
            $providerReference,
            $mapped['code'],
            $mapped['message'],
            $durationMs,
        );

        $fresh = $transaction->fresh();

        $this->transactionEventService->record(
            $fresh,
            TransactionEventService::TYPE_FULFILLMENT_PROVIDER_SUCCEEDED,
            'Fulfillment completed successfully.',
            $actor,
        );

        $this->transactionEventService->record(
            $fresh,
            TransactionEventService::TYPE_FULFILLED,
            'Transaction fulfilled.',
            $actor,
        );

        if ($isRetry) {
            $this->transactionNotificationService->sendRetrySuccess($fresh);
        } else {
            $this->transactionNotificationService->sendDeliverySuccess($fresh);
        }

        $this->transactionNotificationService->sendReceipt($fresh);

        $this->ledgerPostingService->postFulfillmentRecognized($fresh, $attempt);

        return $fresh;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $payload
     *
     * @throws FulfillmentException
     */
    private function markFailedAndThrow(
        Transaction $transaction,
        FulfillmentAttempt $attempt,
        array $response,
        array $payload,
        string $reason,
        string $actor,
        int $durationMs,
        ?string $providerCode,
    ): Transaction {
        $this->fulfillmentAttemptRecorder->markConfirmedFailed(
            $attempt,
            $response,
            $providerCode,
            $reason,
            $reason,
            $durationMs,
        );

        $transaction->update([
            'status' => TransactionStatus::FAILED,
            'failure_reason' => $reason,
            'response_payload' => array_merge(
                (array) $transaction->response_payload,
                ['fulfillment' => $response],
            ),
        ]);

        $fresh = $transaction->fresh();

        $this->transactionEventService->record(
            $fresh,
            TransactionEventService::TYPE_FULFILLMENT_PROVIDER_FAILED,
            'Fulfillment failed.',
            $actor,
            ['reason' => $reason],
        );

        $this->transactionEventService->record(
            $fresh,
            TransactionEventService::TYPE_FULFILLMENT_FAILED,
            'Fulfillment failed.',
            $actor,
            ['reason' => $reason],
        );

        $this->transactionNotificationService->sendDeliveryFailure($fresh);
        $this->transactionNotificationService->sendReceipt($fresh);

        throw new FulfillmentException($reason, 'VTPASS_FULFILLMENT_FAILED');
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @param  array<string, mixed>  $payload
     *
     * @throws FulfillmentException
     */
    private function markUncertainAndThrow(
        Transaction $transaction,
        FulfillmentAttempt $attempt,
        array $payload,
        ?array $response,
        string $reason,
        int $durationMs,
        ?string $providerCode,
    ): Transaction {
        $this->fulfillmentAttemptRecorder->markUncertain(
            $attempt,
            $payload,
            $response,
            $reason,
            $durationMs,
            null,
            $providerCode,
        );

        $transaction->update([
            'status' => TransactionStatus::FULFILLMENT_PENDING,
            'failure_reason' => $reason,
            'response_payload' => $response
                ? array_merge((array) $transaction->response_payload, ['fulfillment' => $response])
                : $transaction->response_payload,
        ]);

        $this->transactionEventService->record(
            $transaction->fresh(),
            TransactionEventService::TYPE_FULFILLMENT_PROVIDER_UNCERTAIN,
            'Fulfillment provider outcome uncertain.',
            'system',
            ['request_id' => $attempt->request_id, 'reason' => $reason],
        );

        throw new FulfillmentException($reason, 'FULFILLMENT_UNCERTAIN');
    }

    private function resolveAdapter(string $productType): FulfillmentAdapterInterface
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($productType)) {
                return $adapter;
            }
        }

        throw new FulfillmentException(
            'No fulfillment adapter found for product type.',
            'UNSUPPORTED_PRODUCT_TYPE',
        );
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $payload
     */
    private function resolveFulfillmentReference(array $response, array $payload): string
    {
        return (string) (
            data_get($response, 'requestId')
            ?? data_get($response, 'content.transactions.transactionId')
            ?? data_get($response, 'content.transactions.requestId')
            ?? $payload['request_id']
        );
    }

    private function fulfillmentStatus(Transaction $transaction): string
    {
        return match ($transaction->status) {
            TransactionStatus::FULFILLED => 'fulfilled',
            TransactionStatus::FULFILLMENT_PENDING => 'pending',
            TransactionStatus::FAILED => 'failed',
            TransactionStatus::PAYMENT_SUCCESS => 'awaiting_delivery',
            default => 'not_started',
        };
    }
}
