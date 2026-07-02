<?php

namespace App\Services;

class FeeService
{
    public const CONVENIENCE_FEE = 100;

    public function convenienceFeeFor(string $productType): int
    {
        return match ($productType) {
            'airtime', 'data', 'electricity' => self::CONVENIENCE_FEE,
            default => self::CONVENIENCE_FEE,
        };
    }

    public function gatewayFee(): int
    {
        return 0;
    }

    public function payableAmount(int $productAmount, int $convenienceFee, int $gatewayFee): int
    {
        return $productAmount + $convenienceFee + $gatewayFee;
    }
}
