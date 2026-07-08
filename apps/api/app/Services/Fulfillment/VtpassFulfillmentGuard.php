<?php

namespace App\Services\Fulfillment;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;
use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\SystemSettingsService;
use App\Support\Fulfillment\VTPassEnvironment;
use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;

class VtpassFulfillmentGuard
{
    /** @var array<string, string> */
    private const SERVICE_FLAGS = [
        'airtime' => FeatureFlagKeys::SERVICE_AIRTIME_ENABLED,
        'data' => FeatureFlagKeys::SERVICE_DATA_ENABLED,
        'electricity' => FeatureFlagKeys::SERVICE_ELECTRICITY_ENABLED,
    ];

    /** @var array<string, string> */
    private const PROVIDER_FLAGS = [
        'airtime' => FeatureFlagKeys::PROVIDER_VTPASS_AIRTIME_ENABLED,
        'data' => FeatureFlagKeys::PROVIDER_VTPASS_DATA_ENABLED,
        'electricity' => FeatureFlagKeys::PROVIDER_VTPASS_ELECTRICITY_ENABLED,
    ];

    public function __construct(
        private readonly FeatureFlagService $featureFlags,
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    /**
     * @throws FulfillmentException
     */
    public function assertCanFulfill(Transaction $transaction): void
    {
        $productType = (string) $transaction->product_type;

        $this->assertProductEnabled($productType);
        $this->assertLiveSafetyLimit($transaction);
    }

    /**
     * @throws FulfillmentException
     */
    private function assertProductEnabled(string $productType): void
    {
        $serviceFlag = self::SERVICE_FLAGS[$productType] ?? null;
        $providerFlag = self::PROVIDER_FLAGS[$productType] ?? null;

        if ($serviceFlag === null || $providerFlag === null) {
            throw new FulfillmentException(
                'This product type is not supported for VTPass fulfillment.',
                'UNSUPPORTED_PRODUCT_TYPE',
            );
        }

        if (! $this->featureFlags->isEnabled($serviceFlag, true)) {
            throw new FulfillmentException(
                ucfirst($productType).' purchases are temporarily unavailable.',
                'SERVICE_DISABLED',
            );
        }

        if (! $this->featureFlags->isEnabled($providerFlag, true)) {
            throw new FulfillmentException(
                ucfirst($productType).' fulfillment is not enabled for live VTPass yet.',
                'VTPASS_PRODUCT_NOT_READY',
            );
        }
    }

    /**
     * @throws FulfillmentException
     */
    private function assertLiveSafetyLimit(Transaction $transaction): void
    {
        if (! VTPassEnvironment::isProduction()) {
            return;
        }

        if (! $this->systemSettings->getBool(SystemSettingKeys::VTPASS_LIVE_SAFETY_MODE, true)) {
            return;
        }

        $maxAmount = max(
            50,
            $this->systemSettings->getInt(SystemSettingKeys::VTPASS_LIVE_TEST_MAX_AMOUNT, 500),
        );
        $productAmount = (int) $transaction->product_amount;

        if ($productAmount > $maxAmount) {
            throw new FulfillmentException(
                'Live fulfillment is limited to smaller test amounts during the initial rollout. Please try a lower amount or contact support.',
                'VTPASS_LIVE_SAFETY_LIMIT',
            );
        }
    }

    public function isLiveSafetyModeEnabled(): bool
    {
        return VTPassEnvironment::isProduction()
            && $this->systemSettings->getBool(SystemSettingKeys::VTPASS_LIVE_SAFETY_MODE, true);
    }

    public function liveTestMaxAmount(): int
    {
        return max(
            50,
            $this->systemSettings->getInt(SystemSettingKeys::VTPASS_LIVE_TEST_MAX_AMOUNT, 500),
        );
    }
}
