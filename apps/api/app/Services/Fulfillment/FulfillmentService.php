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

class FulfillmentService
{
    /** @var array<int, FulfillmentAdapterInterface> */
    private array $adapters;

    public function __construct(
        private readonly VTPassService $vtpassService,
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
    public function fulfill(Transaction $transaction): Transaction
    {
        if ($transaction->status !== TransactionStatus::PAYMENT_SUCCESS) {
            throw new FulfillmentException(
                'Transaction must be payment_success before fulfillment.',
                'INVALID_TRANSACTION_STATUS',
            );
        }

        $this->vtpassService->assertConfigured();

        $adapter = $this->resolveAdapter($transaction->product_type);
        $payload = $adapter->buildPayload($transaction);

        $transaction->update([
            'status' => TransactionStatus::FULFILLMENT_PENDING,
            'fulfillment_provider' => 'vtpass',
            'failure_reason' => null,
        ]);

        try {
            $response = $this->vtpassService->pay($payload);

            if ($this->isSuccessfulResponse($response)) {
                $transaction->update([
                    'status' => TransactionStatus::FULFILLED,
                    'fulfillment_provider' => 'vtpass',
                    'fulfillment_reference' => $this->resolveFulfillmentReference($response, $payload),
                    'response_payload' => array_merge(
                        (array) $transaction->response_payload,
                        ['fulfillment' => $response],
                    ),
                    'failure_reason' => null,
                ]);

                return $transaction->fresh();
            }

            $reason = $this->resolveFailureReason($response);

            $transaction->update([
                'status' => TransactionStatus::FAILED,
                'failure_reason' => $reason,
                'response_payload' => array_merge(
                    (array) $transaction->response_payload,
                    ['fulfillment' => $response],
                ),
            ]);

            throw new FulfillmentException($reason, 'VTPASS_FULFILLMENT_FAILED');
        } catch (VTPassException $exception) {
            $transaction->update([
                'status' => TransactionStatus::FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }
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
     */
    private function isSuccessfulResponse(array $response): bool
    {
        if ((string) data_get($response, 'code') === '000') {
            return true;
        }

        $status = strtolower((string) data_get($response, 'content.transactions.status', ''));

        return in_array($status, ['delivered', 'successful', 'success'], true);
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

    /**
     * @param  array<string, mixed>  $response
     */
    private function resolveFailureReason(array $response): string
    {
        return (string) (
            data_get($response, 'response_description')
            ?? data_get($response, 'content.transactions.product_name')
            ?? data_get($response, 'message')
            ?? 'VTPass fulfillment failed.'
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
