<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Exceptions\FraudCheckException;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    public function __construct(
        private readonly TransactionReferenceGenerator $referenceGenerator,
        private readonly FeeService $feeService,
        private readonly FraudService $fraudService,
    ) {
    }

    /**
     * @throws FraudCheckException
     */
    public function initializeCheckout(array $input, ?string $ipAddress, ?string $userAgent): Transaction
    {
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

        return DB::transaction(function () use ($input, $productAmount, $convenienceFee, $gatewayFee, $payableAmount, $ipAddress, $userAgent, $productType) {
            $reference = $this->referenceGenerator->generate();

            while (Transaction::query()->where('reference', $reference)->exists()) {
                $reference = $this->referenceGenerator->generate();
            }

            return Transaction::query()->create([
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
                'request_payload' => $input['payload'] ?? [],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'verified_phone' => false,
            ]);
        });
    }

    public function toCheckoutResponse(Transaction $transaction): array
    {
        return [
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'product_amount' => $transaction->product_amount,
            'convenience_fee' => $transaction->convenience_fee,
            'gateway_fee' => $transaction->gateway_fee,
            'payable_amount' => $transaction->payable_amount,
            'currency' => $transaction->currency,
            'status' => $transaction->status,
            'payment_status' => 'payment integration coming next',
        ];
    }

    public function toDetailResponse(Transaction $transaction): array
    {
        return [
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
            'fulfillment_reference' => $transaction->fulfillment_reference,
            'failure_reason' => $transaction->failure_reason,
            'verified_phone' => $transaction->verified_phone,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'updated_at' => $transaction->updated_at?->toIso8601String(),
        ];
    }
}
