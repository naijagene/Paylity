<?php

namespace Tests;

use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\SystemSettingsService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var array<string, string>
     */
    private const INTEGRATED_FEATURE_FLAG_DEFAULTS = [
        'FEATURE_PAYSTACK' => 'false',
        'FEATURE_VTPASS' => 'false',
        'FEATURE_VTPASS_AUTO_FULFILL' => 'false',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        app(FeatureFlagService::class)->forgetCache();
        app(SystemSettingsService::class)->forgetCache();
    }

    protected function tearDown(): void
    {
        $this->restoreIntegratedFeatureFlagEnvironment();

        parent::tearDown();
    }

    /**
     * @param  array<string, bool>  $flags
     */
    protected function withIntegratedFeatureFlags(array $flags): void
    {
        foreach ($flags as $envKey => $enabled) {
            $value = $enabled ? 'true' : 'false';
            putenv("{$envKey}={$value}");
            $_ENV[$envKey] = $value;
            $_SERVER[$envKey] = $value;
        }

        app(FeatureFlagService::class)->forgetCache();
    }

    protected function restoreIntegratedFeatureFlagEnvironment(): void
    {
        foreach (self::INTEGRATED_FEATURE_FLAG_DEFAULTS as $envKey => $value) {
            putenv("{$envKey}={$value}");
            $_ENV[$envKey] = $value;
            $_SERVER[$envKey] = $value;
        }

        app(FeatureFlagService::class)->forgetCache();
    }
}
