<?php

namespace App\Services;

use App\Enums\OtpPurpose;
use App\Enums\TransactionStatus;
use App\Exceptions\FraudCheckException;
use App\Exceptions\PaystackConfigurationException;
use App\Exceptions\PaystackException;
use App\Exceptions\ProductCatalogValidationException;
use App\Models\Transaction;
use App\Services\Catalog\ProductCatalogService;
use App\Services\Fulfillment\FulfillmentPayloadExtractor;
use App\Services\Launch\LaunchModeService;
use App\Services\Marketing\LaunchVoucherService;
use App\Services\Marketing\MarketingEventService;
use App\Services\Otp\OtpService;
use App\Services\Payments\PaystackService;
use App\Services\Platform\PurchasePolicyContext;
use App\Services\Platform\PurchasePolicyService;
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
        private readonly PurchasePolicyService $purchasePolicyService,
        private readonly OtpService $otpService,
        private readonly LaunchModeService $launchModeService,
        private readonly LaunchVoucherService $launchVoucherService,
        private readonly MarketingEventService $marketingEventService,
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
        $customerPhone = (string) $input['customer_phone'];
        $voucherCode = trim((string) ($input['voucher_code'] ?? ''));
        $deviceId = trim((string) ($input['device_id'] ?? ''));

        if ($voucherCode !== '' && $productType !== 'airtime') {
            throw new FraudCheckException(
                'Launch vouchers are currently available for airtime only.',
                'VOUCHER_AIRTIME_ONLY',
            );
        }

        $verifiedPhone = false;
        $policyEvaluation = $this->purchasePolicyService->evaluate(
            new PurchasePolicyContext(
                productAmount: $productAmount,
                customerPhone: $customerPhone,
                ipAddress: $ipAddress,
            ),
        );

        if ($policyEvaluation->otpRequired) {
            $verificationToken = trim((string) ($input['verification_token'] ?? ''));

            if ($verificationToken === '') {
                throw new FraudCheckException(
                    'OTP verification is required for this purchase.',
                    'OTP_REQUIRED',
                );
            }

            $otpRecord = $this->otpService->assertValidVerificationToken(
                verificationToken: $verificationToken,
                phone: $customerPhone,
                productAmount: $productAmount,
                purpose: OtpPurpose::CHECKOUT,
            );

            $this->otpService->consumeVerificationToken($otpRecord);
            $verifiedPhone = true;
        }

        $this->fraudService->assertCanInitialize(
            productAmount: $productAmount,
            customerPhone: $customerPhone,
            ipAddress: $ipAddress,
            verifiedPhone: $verifiedPhone,
        );

        $validatedPayload = $this->productCatalogService->validateAndEnrichCheckout(
            productType: $productType,
            productAmount: $productAmount,
            payload: (array) ($input['payload'] ?? []),
        );

        $transaction = DB::transaction(function () use ($input, $productAmount, $ipAddress, $userAgent, $productType, $validatedPayload, $verifiedPhone, $voucherCode, $deviceId) {
            $reference = $this->referenceGenerator->generate();

            while (Transaction::query()->where('reference', $reference)->exists()) {
                $reference = $this->referenceGenerator->generate();
            }

            $convenienceFee = $this->feeService->convenienceFeeFor($productType);
            $gatewayFee = 0;
            $payableAmount = $productAmount + $convenienceFee;
            $voucherDiscountAmount = 0;
            $launchVoucherId = null;
            $storedVoucherCode = null;

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
                'voucher_code' => null,
                'voucher_discount_amount' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::CREATED,
                'request_payload' => $validatedPayload,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'verified_phone' => $verifiedPhone,
            ]);

            if ($voucherCode !== '') {
                $reservation = $this->launchVoucherService->reserveForTransaction(
                    $transaction,
                    $voucherCode,
                    $deviceId !== '' ? $deviceId : null,
                );

                $quote = $reservation['quote'];
                $launchVoucherId = $reservation['voucher']->id;
                $voucherDiscountAmount = $reservation['discount_amount'];
                $storedVoucherCode = $reservation['voucher']->code;
                $convenienceFee = $quote['convenience_fee'];
                $gatewayFee = $quote['gateway_fee'];
                $payableAmount = $quote['payable_amount'];

                $transaction->update([
                    'launch_voucher_id' => $launchVoucherId,
                    'voucher_code' => $storedVoucherCode,
                    'voucher_discount_amount' => $voucherDiscountAmount,
                    'convenience_fee' => $convenienceFee,
                    'gateway_fee' => $gatewayFee,
                    'payable_amount' => $payableAmount,
                ]);
            } else {
                $quote = $this->feeService->quote($productType, $productAmount);
                $transaction->update([
                    'convenience_fee' => $quote['convenience_fee'],
                    'gateway_fee' => $quote['gateway_fee'],
                    'payable_amount' => $quote['payable_amount'],
                ]);
            }

            $freshTransaction = $transaction->fresh();
            $network = strtoupper((string) data_get($freshTransaction->request_payload, 'network', ''));

            $this->launchModeService->assertCheckoutAllowed(
                productType: $productType,
                payableAmount: (int) $freshTransaction->payable_amount,
                productAmount: (int) $freshTransaction->product_amount,
                network: $network !== '' ? $network : null,
            );

            $this->transactionEventService->record(
                $freshTransaction,
                TransactionEventService::TYPE_CREATED,
                'Transaction created.',
            );

            return $transaction->fresh();
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

            if (trim($result['authorization_url']) === '') {
                throw new PaystackException(
                    'Paystack did not return a payment authorization URL.',
                    'PAYSTACK_REDIRECT_UNAVAILABLE',
                );
            }

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

            $this->launchVoucherService->releaseReservation($transaction->fresh(), 'paystack_init_failed');

            throw $exception;
        }
    }

    public function toCheckoutResponse(Transaction $transaction): array
    {
        $voucherDiscount = (int) ($transaction->voucher_discount_amount ?? 0);
        $netProductAmount = max(0, (int) $transaction->product_amount - $voucherDiscount);

        $response = [
            'reference' => $transaction->reference,
            'product_type' => $transaction->product_type,
            'product_amount' => $transaction->product_amount,
            'net_product_amount' => $netProductAmount,
            'convenience_fee' => $transaction->convenience_fee,
            'gateway_fee' => $transaction->gateway_fee,
            'payable_amount' => $transaction->payable_amount,
            'voucher_code_masked' => $this->launchVoucherService->maskCode($transaction->voucher_code),
            'voucher_discount_amount' => $voucherDiscount,
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
            'voucher_code_masked' => $this->launchVoucherService->maskCode($transaction->voucher_code),
            'voucher_discount_amount' => (int) ($transaction->voucher_discount_amount ?? 0),
            'net_product_amount' => max(0, (int) $transaction->product_amount - (int) ($transaction->voucher_discount_amount ?? 0)),
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
