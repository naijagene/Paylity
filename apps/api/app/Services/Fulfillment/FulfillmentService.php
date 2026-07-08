<?php

namespace App\Services\Fulfillment;

use App\Enums\TransactionStatus;
use App\Exceptions\FulfillmentException;
use App\Exceptions\VTPassException;
use App\Models\Transaction;
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

        return $this->fulfill($transaction);
    }

    /**
     * @throws FulfillmentException
     * @throws VTPassException
     */
    public function fulfill(Transaction $transaction, string $actor = 'system', bool $isRetry = false): Transaction
    {
        if (! in_array($transaction->status, [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FAILED,
        ], true)) {
            throw new FulfillmentException(
                'Transaction must be payment_success or failed before fulfillment.',
                'INVALID_TRANSACTION_STATUS',
            );
        }

        $this->vtpassFulfillmentGuard->assertCanFulfill($transaction);
        $this->vtpassService->assertConfigured();

        $adapter = $this->resolveAdapter($transaction->product_type);
        $payload = $adapter->buildPayload($transaction);

        $transaction->update([
            'status' => TransactionStatus::FULFILLMENT_PENDING,
            'fulfillment_provider' => 'vtpass',
            'failure_reason' => null,
        ]);

        $this->transactionEventService->record(
            $transaction->fresh(),
            $isRetry
                ? TransactionEventService::TYPE_FULFILLMENT_RETRY
                : TransactionEventService::TYPE_FULFILLMENT_PENDING,
            $isRetry ? 'Fulfillment retry started.' : 'Fulfillment started.',
            $actor,
        );

        $startedAt = microtime(true);

        try {
            $response = $this->vtpassService->pay($payload);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            if ($this->responseMapper->isSuccessful($response)) {
                $transaction->update([
                    'status' => TransactionStatus::FULFILLED,
                    'fulfillment_provider' => 'vtpass',
                    'fulfillment_reference' => $this->resolveFulfillmentReference($response, $payload),
                    'response_payload' => array_merge(
                        (array) $transaction->response_payload,
                        ['fulfillment' => $response],
                    ),
                    'failure_reason' => null,
                    'fulfilled_at' => now(),
                ]);

                $fresh = $transaction->fresh();

                $this->fulfillmentAttemptRecorder->record(
                    $fresh,
                    'success',
                    $actor,
                    (string) ($payload['request_id'] ?? null),
                    $payload,
                    $response,
                    null,
                    $durationMs,
                );

                $this->transactionEventService->record(
                    $fresh,
                    TransactionEventService::TYPE_FULFILLED,
                    'Fulfillment completed successfully.',
                    $actor,
                );

                if ($isRetry) {
                    $this->transactionNotificationService->sendRetrySuccess($fresh);
                } else {
                    $this->transactionNotificationService->sendDeliverySuccess($fresh);
                }

                $this->transactionNotificationService->sendReceipt($fresh);

                return $fresh;
            }

            $reason = $this->responseMapper->failureReason($response);

            $transaction->update([
                'status' => TransactionStatus::FAILED,
                'failure_reason' => $reason,
                'response_payload' => array_merge(
                    (array) $transaction->response_payload,
                    ['fulfillment' => $response],
                ),
            ]);

            $fresh = $transaction->fresh();

            $this->fulfillmentAttemptRecorder->record(
                $fresh,
                'failed',
                $actor,
                (string) ($payload['request_id'] ?? null),
                $payload,
                $response,
                $reason,
                $durationMs,
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
        } catch (VTPassException $exception) {
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

            $transaction->update([
                'status' => TransactionStatus::FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);

            $fresh = $transaction->fresh();

            $this->fulfillmentAttemptRecorder->record(
                $fresh,
                'error',
                $actor,
                (string) ($payload['request_id'] ?? null),
                $payload,
                null,
                $exception->getMessage(),
                $durationMs,
            );

            $this->transactionEventService->record(
                $fresh,
                TransactionEventService::TYPE_FULFILLMENT_FAILED,
                'Fulfillment provider error.',
                $actor,
                ['reason' => $exception->getMessage()],
            );

            $this->transactionNotificationService->sendDeliveryFailure($fresh);

            throw $exception;
        }
    }

    /**
     * @throws FulfillmentException
     * @throws VTPassException
     */
    public function retryFulfillment(Transaction $transaction, string $actor = 'operator'): Transaction
    {
        return $this->fulfill($transaction, $actor, true);
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
