<?php

namespace App\Services\Platform;

use App\Models\FeatureFlag;
use App\Support\Platform\FeatureFlagKeys;
use Illuminate\Support\Facades\Cache;

class FeatureFlagService
{
    private const CACHE_KEY = 'platform.feature_flags';

    /**
     * @var array<string, string|null>
     */
    private const ENV_OVERRIDES = [
        FeatureFlagKeys::PAYSTACK => 'FEATURE_PAYSTACK',
        FeatureFlagKeys::VTPASS => 'FEATURE_VTPASS',
        FeatureFlagKeys::VTPASS_AUTO_FULFILL => 'FEATURE_VTPASS_AUTO_FULFILL',
    ];

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return FeatureFlag::query()
                ->orderBy('key')
                ->get()
                ->mapWithKeys(fn (FeatureFlag $flag): array => [
                    $flag->key => (bool) $flag->enabled,
                ])
                ->all();
        });
    }

    public function isEnabled(string $key, bool $default = false): bool
    {
        if ($this->hasEnvironmentOverride($key)) {
            return $this->resolveEnvironmentOverride($key);
        }

        $flags = $this->all();

        return array_key_exists($key, $flags) ? (bool) $flags[$key] : $default;
    }

    public function set(string $key, bool $enabled): FeatureFlag
    {
        $flag = FeatureFlag::query()->updateOrCreate(
            ['key' => $key],
            ['enabled' => $enabled],
        );

        $this->forgetCache();

        return $flag;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function hasEnvironmentOverride(string $key): bool
    {
        $envKey = self::ENV_OVERRIDES[$key] ?? null;

        if ($envKey === null) {
            return false;
        }

        $value = env($envKey);

        return $value !== null && $value !== '';
    }

    private function resolveEnvironmentOverride(string $key): bool
    {
        $envKey = self::ENV_OVERRIDES[$key] ?? null;

        if ($envKey === null) {
            return false;
        }

        return filter_var(env($envKey), FILTER_VALIDATE_BOOL);
    }
}
