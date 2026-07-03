<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Exceptions\FraudCheckException;
use App\Exceptions\PaystackConfigurationException;
use App\Exceptions\PaystackException;
use App\Exceptions\ProductCatalogValidationException;
use App\Models\Transaction;
use App\Services\Catalog\ProductCatalogService;
use App\Services\Fulfillment\FulfillmentPayloadExtractor;
use App\Services\Payments\PaystackService;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    private const TERMINAL_STATUSES = [
        TransactionStatus::PAYMENT_FAILED,
        TransactionStatus::FULFILLED,
        TransactionStatus::FAILED,
        TransactionStatus::CANCELLED,
    ];

    public function __construct(
        private readonly TransactionReferenceGenerator $referenceGenerator,
        private readonly FeeService $feeService,
        private readonly FraudService $fraudService,
        private readonly PaystackService $paystackService,
        private readonly FulfillmentPayloadExtractor $fulfillmentPayloadExtractor,
        private readonly ReceiptService $receiptService,
        private readonly TransactionEventService $transactionEventService,
        private readonly ProductCatalogService $productCatalogService,
    ) {
    }

    /**
     * @throws FraudCheckException
     * @throws PaystackConfigurationException
     * @throws PaystackException
     * @throws ProductCatalogValidationException
     */
    public function initializeCheckout(array $input, ?string $ipAddress, ?string $userAgent): Transaction
    {
        if ($this->paystackService->isEnabled() && ! $this->paystackService->hasSecretKey()) {
            throw new PaystackConfigurationException();
        }

        $productType = $input['product_type'];
        $productAmount = (int) $input['product_amount'];
        $convenienceFee = $this->feeService->convenienceFeeFor($productType);
        $gatewayFee = $this->feeService->gatewayFee();
        $payableAmount = $this->feeService->payableAmount(
            $productAmount,
            $convenienceFee,
            $gatewayFee,
        );

        $this->fraudService->assertCanInitialize(
            productAmount: $productAmount,
            customerPhone: $input['customer_phone'],
            ipAddress: $ipAddress,
            verifiedPhone: false,
        );

        $validatedPayload = $this->productCatalogService->validateAndEnrichCheckout(
            productType: $productType,
            productAmount: $productAmount,
            payload: (array) ($input['payload'] ?? []),
        );

        $transaction = DB::transaction(function () use ($input, $productAmount, $convenienceFee, $gatewayFee, $payableAmount, $ipAddress, $userAgent, $productType, $validatedPayload) {
            $reference = $this->referenceGenerator->generate();

            while (Transaction::query()->where('reference', $reference)->exists()) {
                $reference = $this->referenceGenerator->generate();
            }

            $transaction = Transaction::query()->create([
                'reference' => $reference,
                'product_type' => $productType,
                'customer_phone' => $input['customer_phone'],
                'customer_email' => $input['customer_email'] ?? null,
                'customer_name' => $input['customer_name'] ?? null,
                'product_amount' => $productAmount,
                'convenience_fee' => $convenienceFee,
                'gateway_fee' => $gatewayFee,
                'payable_amount' => $payableAmount,
                'currency' => 'NGN',
                'status' => TransactionStatus::CREATED,
                'request_payload' => $validatedPayload,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'verified_phone' => false,
            ]);

            $this->transactionEventService->record(
                $transaction,
                TransactionEventService::TYPE_CREATED,
                'Transaction created.',
            );

            return $transaction;
        });

        if ($this->paystackService->isEnabled()) {
            $this->initializePaystackPayment($transaction);
        }

        return $transaction->fresh();
    }

    /**
     * @throws PaystackException
     */
    private function initializePaystackPayment(Transaction $transaction): void
    {
        try {
            $result = $this->paystackService->initializeTransaction($transaction);

            $transaction->update([
                'status' => TransactionStatus::PAYMENT_PENDING,
                'payment_provider' => 'paystack',
                'payment_reference' => $result['reference'],
                'payment_authorization_url' => $result['authorization_url'],
                'response_payload' => $result['raw'],
                'failure_reason' => null,
            ]);
        } catch (PaystackException $exception) {
            $transaction->update([
                'status' => TransactionStatus::FAILED,
                'failure_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function toCheckoutResponse(Transaction $transaction): array
    {
        $response = [
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'product_amount' => $transaction->product_amount,
            'convenience_fee' => $transaction->convenience_fee,
            'gateway_fee' => $transaction->gateway_fee,
            'payable_amount' => $transaction->payable_amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'payment_provider' => $transaction->payment_provider,
        ];

        if ($transaction->payment_authorization_url) {
            $response['authorization_url'] = $transaction->payment_authorization_url;
            $response['access_code'] = data_get(
                $transaction->response_payload,
                'data.access_code',
            );
        } else {
            $response['payment_status'] = 'payment integration coming next';
        }

        return $response;
    }

    public function toDetailResponse(Transaction $transaction): array
    {
        $response = [
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
            'verified_phone' => $transaction->verified_phone,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'updated_at' => $transaction->updated_at?->toIso8601String(),
        ];

        $fulfillmentDetails = $this->fulfillmentPayloadExtractor->extractPublicDetails($transaction);

        if ($fulfillmentDetails !== null) {
            $response['fulfillment_details'] = $fulfillmentDetails;
        }

        $receipt = $this->receiptService->buildReceiptPayload($transaction);

        if ($receipt !== null) {
            $response['receipt'] = $receipt;
        }

        $response['timeline'] = $this->transactionEventService
            ->timelineFor($transaction)
            ->values()
            ->all();
        $response['is_terminal'] = in_array($transaction->status, self::TERMINAL_STATUSES, true);
        $response['poll_interval_seconds'] = 5;

        return $response;
    }
}
