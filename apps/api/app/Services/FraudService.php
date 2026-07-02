<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Exceptions\FraudCheckException;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class FraudService
{
    public const GUEST_MAX_PRODUCT_AMOUNT = 10_000;

    public const DAILY_PHONE_PRODUCT_LIMIT = 20_000;

    public const DAILY_IP_PRODUCT_LIMIT = 30_000;

    /**
     * @throws FraudCheckException
     */
    public function assertCanInitialize(
        int $productAmount,
        string $customerPhone,
        ?string $ipAddress,
        bool $verifiedPhone = false,
    ): void {
        if (! $verifiedPhone && $productAmount > self::GUEST_MAX_PRODUCT_AMOUNT) {
            throw new FraudCheckException(
                'Guest product amount is limited to ₦10,000.',
                'GUEST_LIMIT_EXCEEDED',
            );
        }

        $phoneTotal = $this->sumRecentProductAmount('customer_phone', $customerPhone);

        if (($phoneTotal + $productAmount) > self::DAILY_PHONE_PRODUCT_LIMIT) {
            throw new FraudCheckException(
                'Daily limit reached for this phone number.',
                'PHONE_DAILY_LIMIT_EXCEEDED',
            );
        }

        if ($ipAddress) {
            $ipTotal = $this->sumRecentProductAmount('ip_address', $ipAddress);

            if (($ipTotal + $productAmount) > self::DAILY_IP_PRODUCT_LIMIT) {
                throw new FraudCheckException(
                    'Daily limit reached for this device.',
                    'IP_DAILY_LIMIT_EXCEEDED',
                );
            }
        }
    }

    private function sumRecentProductAmount(string $column, string $value): int
    {
        return (int) Transaction::query()
            ->where($column, $value)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->whereIn('status', [
                TransactionStatus::PAYMENT_PENDING,
                TransactionStatus::PAYMENT_SUCCESS,
                TransactionStatus::FULFILLMENT_PENDING,
                TransactionStatus::FULFILLED,
            ])
            ->sum('product_amount');
    }
}
