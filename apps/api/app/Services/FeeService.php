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

    /**
     * Recover Paystack gateway fee against pre-gateway charge (product + convenience),
     * not product amount alone. For voucher checkout, pass discounted product subtotal.
     */
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
     * @return array{
     *     convenience_fee: int,
     *     gateway_fee: int,
     *     payable_amount: int,
     *     voucher_discount_amount: int,
     *     net_product_amount: int,
     *     pre_gateway_charge: int
     * }
     */
    public function quote(string $productType, int $productAmount, int $voucherDiscountAmount = 0): array
    {
        $voucherDiscountAmount = max(0, min($voucherDiscountAmount, $productAmount));
        $netProductAmount = max(0, $productAmount - $voucherDiscountAmount);
        $convenienceFee = $this->convenienceFeeFor($productType);
        $preGatewayCharge = $netProductAmount + $convenienceFee;
        $gatewayFee = $this->gatewayFeeFor($netProductAmount, $convenienceFee);

        return [
            'convenience_fee' => $convenienceFee,
            'gateway_fee' => $gatewayFee,
            'payable_amount' => $this->payableAmount($netProductAmount, $convenienceFee, $gatewayFee),
            'voucher_discount_amount' => $voucherDiscountAmount,
            'net_product_amount' => $netProductAmount,
            'pre_gateway_charge' => $preGatewayCharge,
        ];
    }
}
