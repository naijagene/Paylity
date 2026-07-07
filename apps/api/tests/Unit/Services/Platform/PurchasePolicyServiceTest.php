<?php

namespace Tests\Unit\Services\Platform;

use App\Exceptions\FraudCheckException;
use App\Models\FeatureFlag;
use App\Models\SystemSetting;
use App\Services\Platform\PurchasePolicyContext;
use App\Services\Platform\PurchasePolicyService;
use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchasePolicyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PurchasePolicyService::class);

        SystemSetting::query()->insert([
            [
                'key' => SystemSettingKeys::GUEST_CHECKOUT_ENABLED,
                'value' => '1',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => SystemSettingKeys::OTP_ENABLED,
                'value' => '1',
                'type' => 'boolean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => SystemSettingKeys::GUEST_LIMIT,
                'value' => '20000',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => SystemSettingKeys::OTP_THRESHOLD,
                'value' => '10000',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => SystemSettingKeys::REGISTRATION_THRESHOLD,
                'value' => '20000',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => SystemSettingKeys::DAILY_PHONE_PRODUCT_LIMIT,
                'value' => '20000',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => SystemSettingKeys::DAILY_IP_PRODUCT_LIMIT,
                'value' => '30000',
                'type' => 'integer',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(\App\Services\Platform\SystemSettingsService::class)->forgetCache();

        FeatureFlag::query()->create([
            'key' => FeatureFlagKeys::OTP_VERIFICATION,
            'enabled' => true,
        ]);

        app(\App\Services\Platform\FeatureFlagService::class)->forgetCache();
    }

    public function test_evaluate_marks_otp_required_above_threshold(): void
    {
        $evaluation = $this->service->evaluate(new PurchasePolicyContext(
            productAmount: 15_000,
            customerPhone: '08031234567',
        ));

        $this->assertTrue($evaluation->otpRequired);
        $this->assertFalse($evaluation->registrationRequired);
    }

    public function test_evaluate_marks_registration_required_when_accounts_feature_is_enabled(): void
    {
        FeatureFlag::query()->create([
            'key' => FeatureFlagKeys::CUSTOMER_ACCOUNTS,
            'enabled' => true,
        ]);

        app(\App\Services\Platform\FeatureFlagService::class)->forgetCache();

        $evaluation = $this->service->evaluate(new PurchasePolicyContext(
            productAmount: 25_000,
            customerPhone: '08031234567',
            verifiedPhone: true,
        ));

        $this->assertFalse($evaluation->otpRequired);
        $this->assertTrue($evaluation->registrationRequired);
    }

    public function test_assert_can_initialize_allows_amount_at_otp_threshold(): void
    {
        $evaluation = $this->service->assertCanInitialize(new PurchasePolicyContext(
            productAmount: 10_000,
            customerPhone: '08031234567',
            ipAddress: '127.0.0.1',
        ));

        $this->assertFalse($evaluation->otpRequired);
    }

    public function test_assert_can_initialize_blocks_otp_required_amounts(): void
    {
        $this->expectException(FraudCheckException::class);
        $this->expectExceptionMessage('OTP verification is required for this purchase.');

        try {
            $this->service->assertCanInitialize(new PurchasePolicyContext(
                productAmount: 12_000,
                customerPhone: '08031234567',
            ));
        } catch (FraudCheckException $exception) {
            $this->assertSame('OTP_REQUIRED', $exception->errorCode);

            throw $exception;
        }
    }

    public function test_assert_can_initialize_blocks_amounts_above_guest_limit(): void
    {
        $this->expectException(FraudCheckException::class);

        try {
            $this->service->assertCanInitialize(new PurchasePolicyContext(
                productAmount: 25_000,
                customerPhone: '08031234567',
            ));
        } catch (FraudCheckException $exception) {
            $this->assertSame('GUEST_LIMIT_EXCEEDED', $exception->errorCode);

            throw $exception;
        }
    }
}
