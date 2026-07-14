<?php

namespace App\Services\Finance;

use App\Services\Platform\SystemSettingsService;
use App\Support\Finance\Money;
use App\Support\Platform\SystemSettingKeys;

class PaystackGatewayFeeCalculator
{
    public function __construct(
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    public function feeNairaForCheckout(int $productAmountNaira, int $convenienceFeeNaira): int
    {
        return Money::koboToNaira($this->feeKoboForCheckout($productAmountNaira, $convenienceFeeNaira));
    }

    public function feeKoboForPayable(int $payableAmountKobo): int
    {
        $basisPoints = max(0, $this->systemSettings->getInt(
            SystemSettingKeys::FINANCIAL_PAYSTACK_FEE_BASIS_POINTS,
            150,
        ));
        $flatFeeKobo = max(0, $this->systemSettings->getInt(
            SystemSettingKeys::FINANCIAL_PAYSTACK_FEE_FLAT_KOBO,
            10000,
        ));

        $percentageFee = (int) round($payableAmountKobo * ($basisPoints / 10000));

        return $percentageFee + $flatFeeKobo;
    }

    public function feeKoboForCheckout(int $productAmountNaira, int $convenienceFeeNaira): int
    {
        $subtotalKobo = Money::nairaToKobo($productAmountNaira + $convenienceFeeNaira);
        $gatewayKobo = 0;

        for ($iteration = 0; $iteration < 5; $iteration++) {
            $payableKobo = $subtotalKobo + $gatewayKobo;
            $gatewayKobo = $this->feeKoboForPayable($payableKobo);
        }

        return $gatewayKobo;
    }

    /**
     * @return array{
     *     product_amount: int,
     *     convenience_fee: int,
     *     gateway_fee: int,
     *     payable_amount: int,
     *     estimated_paystack_fee_kobo: int,
     *     estimated_gross_margin_kobo: int,
     *     margin_percentage: float,
     *     negative_margin: bool
     * }
     */
    public function auditLaunchAmount(int $productAmountNaira, int $convenienceFeeNaira, int $providerCostNaira): array
    {
        $gatewayFeeNaira = $this->feeNairaForCheckout($productAmountNaira, $convenienceFeeNaira);
        $payableNaira = $productAmountNaira + $convenienceFeeNaira + $gatewayFeeNaira;
        $payableKobo = Money::nairaToKobo($payableNaira);
        $estimatedPaystackFeeKobo = $this->feeKoboForPayable($payableKobo);

        $productKobo = Money::nairaToKobo($productAmountNaira);
        $convenienceKobo = Money::nairaToKobo($convenienceFeeNaira);
        $gatewayRecoveryKobo = Money::nairaToKobo($gatewayFeeNaira);
        $providerCostKobo = Money::nairaToKobo($providerCostNaira);

        $grossMarginKobo = $productKobo
            - $providerCostKobo
            + $convenienceKobo
            + $gatewayRecoveryKobo
            - $estimatedPaystackFeeKobo;

        $marginPercentage = $payableKobo > 0
            ? round(($grossMarginKobo / $payableKobo) * 100, 2)
            : 0.0;

        return [
            'product_amount' => $productAmountNaira,
            'convenience_fee' => $convenienceFeeNaira,
            'gateway_fee' => $gatewayFeeNaira,
            'payable_amount' => $payableNaira,
            'estimated_paystack_fee_kobo' => $estimatedPaystackFeeKobo,
            'estimated_gross_margin_kobo' => $grossMarginKobo,
            'margin_percentage' => $marginPercentage,
            'negative_margin' => $grossMarginKobo < 0,
        ];
    }
}
