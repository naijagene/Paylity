<?php

namespace Tests\Unit\Services\Fulfillment;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;
use App\Services\Fulfillment\VtpassFulfillmentGuard;
use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VtpassFulfillmentGuardTest extends TestCase
{
    use RefreshDatabase;

    private VtpassFulfillmentGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlatformSettingsSeeder::class);
        $this->guard = app(VtpassFulfillmentGuard::class);
    }

    public function test_live_safety_mode_blocks_amount_above_threshold_in_production(): void
    {
        config(['services.vtpass.environment' => 'production']);

        app(\App\Services\Platform\SystemSettingsService::class)->setMany([
            SystemSettingKeys::VTPASS_LIVE_SAFETY_MODE => true,
            SystemSettingKeys::VTPASS_LIVE_TEST_MAX_AMOUNT => 500,
        ]);

        $transaction = $this->makeTransaction(productAmount: 1000);

        $this->expectException(FulfillmentException::class);
        $this->expectExceptionMessage('Live fulfillment is limited to smaller test amounts');

        $this->guard->assertCanFulfill($transaction);
    }

    public function test_live_safety_mode_allows_amount_at_or_below_threshold(): void
    {
        config(['services.vtpass.environment' => 'production']);

        app(\App\Services\Platform\SystemSettingsService::class)->setMany([
            SystemSettingKeys::VTPASS_LIVE_SAFETY_MODE => true,
            SystemSettingKeys::VTPASS_LIVE_TEST_MAX_AMOUNT => 500,
        ]);

        $transaction = $this->makeTransaction(productAmount: 500);

        $this->guard->assertCanFulfill($transaction);

        $this->assertTrue(true);
    }

    public function test_disabled_provider_product_blocks_fulfillment(): void
    {
        app(\App\Services\Platform\FeatureFlagService::class)->set(
            FeatureFlagKeys::PROVIDER_VTPASS_DATA_ENABLED,
            false,
        );

        $transaction = $this->makeTransaction(productType: 'data', productAmount: 100);

        try {
            $this->guard->assertCanFulfill($transaction);
            $this->fail('Expected FulfillmentException was not thrown.');
        } catch (FulfillmentException $exception) {
            $this->assertSame('VTPASS_PRODUCT_NOT_READY', $exception->errorCode);
        }
    }

    private function makeTransaction(
        string $productType = 'airtime',
        int $productAmount = 100,
    ): Transaction {
        return Transaction::query()->make([
            'reference' => 'PYL-20260708-GUARD01',
            'product_type' => $productType,
            'customer_phone' => '08031234567',
            'product_amount' => $productAmount,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => $productAmount + 100,
            'currency' => 'NGN',
            'status' => 'payment_success',
            'verified_phone' => false,
        ]);
    }
}
