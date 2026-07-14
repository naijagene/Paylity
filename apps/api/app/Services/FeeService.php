<?php

namespace App\Services;

use App\Services\Finance\PaystackGatewayFeeCalculator;

class FeeService
{
    public const CONVENIENCE_FEE = 100;

    public function __construct(
        private readonly PaystackGatewayFeeCalculator $paystackGatewayFeeCalculator,
    ) {
    }

    public function convenienceFeeFor(string $productType): int
    {
        return match ($productType) {
            'airtime', 'data', 'electricity' => self::CONVENIENCE_FEE,
            default => self::CONVENIENCE_FEE,
        };
    }

    public function gatewayFeeFor(int $productAmount, int $convenienceFee): int
    {
        if (! (bool) config('services.paystack.enabled')) {
            return 0;
        }

        return $this->paystackGatewayFeeCalculator->feeNairaForCheckout($productAmount, $convenienceFee);
    }

    /** @deprecated Use gatewayFeeFor() with product and convenience amounts. */
    public function gatewayFee(): int
    {
        return 0;
    }

    public function payableAmount(int $productAmount, int $convenienceFee, int $gatewayFee): int
    {
        return $productAmount + $convenienceFee + $gatewayFee;
    }

    /**
     * @return array{convenience_fee: int, gateway_fee: int, payable_amount: int}
     */
    public function quote(string $productType, int $productAmount): array
    {
        $convenienceFee = $this->convenienceFeeFor($productType);
        $gatewayFee = $this->gatewayFeeFor($productAmount, $convenienceFee);

        return [
            'convenience_fee' => $convenienceFee,
            'gateway_fee' => $gatewayFee,
            'payable_amount' => $this->payableAmount($productAmount, $convenienceFee, $gatewayFee),
        ];
    }
}
