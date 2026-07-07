<?php

namespace App\Services\Platform;

use App\Enums\TransactionStatus;
use App\Exceptions\FraudCheckException;
use App\Models\Transaction;
use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Carbon;

class PurchasePolicyContext
{
    public function __construct(
        public readonly int $productAmount,
        public readonly string $customerPhone,
        public readonly ?string $ipAddress = null,
        public readonly bool $verifiedPhone = false,
        public readonly bool $registeredCustomer = false,
    ) {
    }
}

class PurchasePolicyEvaluation
{
    public function __construct(
        public readonly bool $otpRequired,
        public readonly bool $registrationRequired,
        public readonly int $guestLimit,
        public readonly int $otpThreshold,
        public readonly int $registrationThreshold,
    ) {
    }
}

class PurchasePolicyService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
        private readonly FeatureFlagService $featureFlags,
    ) {
    }

    /**
     * @throws FraudCheckException
     */
    public function assertCanInitialize(PurchasePolicyContext $context): PurchasePolicyEvaluation
    {
        if ($this->settings->getBool(SystemSettingKeys::MAINTENANCE_MODE)) {
            throw new FraudCheckException(
                'PAYLITY is temporarily unavailable for checkout.',
                'MAINTENANCE_MODE',
            );
        }

        if (! $this->settings->getBool(SystemSettingKeys::GUEST_CHECKOUT_ENABLED, true)) {
            throw new FraudCheckException(
                'Guest checkout is currently unavailable.',
                'GUEST_CHECKOUT_DISABLED',
            );
        }

        $evaluation = $this->evaluate($context);

        if (! $context->verifiedPhone && $context->productAmount > $evaluation->guestLimit) {
            throw new FraudCheckException(
                sprintf('Guest product amount is limited to ₦%s.', number_format($evaluation->guestLimit)),
                'GUEST_LIMIT_EXCEEDED',
            );
        }

        if ($evaluation->otpRequired && ! $context->verifiedPhone) {
            throw new FraudCheckException(
                'Phone verification is required for this purchase amount.',
                'OTP_REQUIRED',
            );
        }

        if ($evaluation->registrationRequired && ! $context->registeredCustomer) {
            throw new FraudCheckException(
                'Customer registration is required for this purchase amount.',
                'REGISTRATION_REQUIRED',
            );
        }

        $this->assertDailyLimits($context);

        return $evaluation;
    }

    public function evaluate(PurchasePolicyContext $context): PurchasePolicyEvaluation
    {
        $guestLimit = $this->settings->getInt(SystemSettingKeys::GUEST_LIMIT, 10_000);
        $otpThreshold = $this->settings->getInt(SystemSettingKeys::OTP_THRESHOLD, 10_000);
        $registrationThreshold = $this->settings->getInt(SystemSettingKeys::REGISTRATION_THRESHOLD, 20_000);

        $otpRequired = $this->settings->getBool(SystemSettingKeys::OTP_ENABLED)
            && ! $context->verifiedPhone
            && $context->productAmount > $otpThreshold
            && $context->productAmount <= $guestLimit;

        $registrationRequired = $this->featureFlags->isEnabled(FeatureFlagKeys::CUSTOMER_ACCOUNTS)
            && ! $context->registeredCustomer
            && $context->productAmount > $registrationThreshold;

        return new PurchasePolicyEvaluation(
            otpRequired: $otpRequired,
            registrationRequired: $registrationRequired,
            guestLimit: $guestLimit,
            otpThreshold: $otpThreshold,
            registrationThreshold: $registrationThreshold,
        );
    }

    /**
     * @throws FraudCheckException
     */
    private function assertDailyLimits(PurchasePolicyContext $context): void
    {
        $phoneLimit = $this->settings->getInt(SystemSettingKeys::DAILY_PHONE_PRODUCT_LIMIT, 20_000);
        $ipLimit = $this->settings->getInt(SystemSettingKeys::DAILY_IP_PRODUCT_LIMIT, 30_000);

        $phoneTotal = $this->sumRecentProductAmount('customer_phone', $context->customerPhone);

        if (($phoneTotal + $context->productAmount) > $phoneLimit) {
            throw new FraudCheckException(
                'Daily limit reached for this phone number.',
                'PHONE_DAILY_LIMIT_EXCEEDED',
            );
        }

        if ($context->ipAddress) {
            $ipTotal = $this->sumRecentProductAmount('ip_address', $context->ipAddress);

            if (($ipTotal + $context->productAmount) > $ipLimit) {
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
