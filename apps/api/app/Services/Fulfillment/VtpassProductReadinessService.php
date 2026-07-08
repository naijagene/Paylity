<?php

namespace App\Services\Fulfillment;

use App\Services\Platform\FeatureFlagService;
use App\Support\Platform\FeatureFlagKeys;

class VtpassProductReadinessService
{
    /** @var list<string> */
    private const PRODUCT_TYPES = ['airtime', 'data', 'electricity'];

    public function __construct(
        private readonly FeatureFlagService $featureFlags,
    ) {
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function matrix(): array
    {
        $matrix = [];

        foreach (self::PRODUCT_TYPES as $productType) {
            $matrix[$productType] = [
                'service_enabled' => $this->isServiceEnabled($productType),
                'provider_enabled' => $this->isProviderEnabled($productType),
                'ready' => $this->isReady($productType),
            ];
        }

        return $matrix;
    }

    public function isReady(string $productType): bool
    {
        return $this->isServiceEnabled($productType) && $this->isProviderEnabled($productType);
    }

    private function isServiceEnabled(string $productType): bool
    {
        return match ($productType) {
            'airtime' => $this->featureFlags->isEnabled(FeatureFlagKeys::SERVICE_AIRTIME_ENABLED, true),
            'data' => $this->featureFlags->isEnabled(FeatureFlagKeys::SERVICE_DATA_ENABLED, true),
            'electricity' => $this->featureFlags->isEnabled(FeatureFlagKeys::SERVICE_ELECTRICITY_ENABLED, true),
            default => false,
        };
    }

    private function isProviderEnabled(string $productType): bool
    {
        return match ($productType) {
            'airtime' => $this->featureFlags->isEnabled(FeatureFlagKeys::PROVIDER_VTPASS_AIRTIME_ENABLED, true),
            'data' => $this->featureFlags->isEnabled(FeatureFlagKeys::PROVIDER_VTPASS_DATA_ENABLED, false),
            'electricity' => $this->featureFlags->isEnabled(FeatureFlagKeys::PROVIDER_VTPASS_ELECTRICITY_ENABLED, true),
            default => false,
        };
    }
}
