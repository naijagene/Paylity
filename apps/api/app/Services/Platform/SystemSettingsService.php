<?php

namespace App\Services\Platform;

use App\Models\SystemSetting;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Facades\Cache;

class SystemSettingsService
{
    private const CACHE_KEY = 'platform.system_settings';

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return SystemSetting::query()
                ->orderBy('key')
                ->get()
                ->mapWithKeys(fn (SystemSetting $setting): array => [
                    $setting->key => $this->castStoredValue($setting->value, $setting->type),
                ])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string) $this->get($key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function set(string $key, mixed $value): SystemSetting
    {
        $existing = SystemSetting::query()->where('key', $key)->first();
        $type = $existing?->type ?? $this->inferType($value);

        $setting = SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->encodeValue($value, $type),
                'type' => $type,
            ],
        );

        $this->forgetCache();

        return $setting;
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, int|bool>
     */
    public function purchaseThresholds(): array
    {
        return [
            SystemSettingKeys::GUEST_LIMIT => $this->getInt(SystemSettingKeys::GUEST_LIMIT, 10_000),
            SystemSettingKeys::OTP_THRESHOLD => $this->getInt(SystemSettingKeys::OTP_THRESHOLD, 10_000),
            SystemSettingKeys::REGISTRATION_THRESHOLD => $this->getInt(SystemSettingKeys::REGISTRATION_THRESHOLD, 20_000),
            SystemSettingKeys::DAILY_PHONE_PRODUCT_LIMIT => $this->getInt(SystemSettingKeys::DAILY_PHONE_PRODUCT_LIMIT, 20_000),
            SystemSettingKeys::DAILY_IP_PRODUCT_LIMIT => $this->getInt(SystemSettingKeys::DAILY_IP_PRODUCT_LIMIT, 30_000),
            SystemSettingKeys::MIN_PRODUCT_AMOUNT => $this->getInt(SystemSettingKeys::MIN_PRODUCT_AMOUNT, 50),
            SystemSettingKeys::MAX_PRODUCT_AMOUNT => $this->getInt(SystemSettingKeys::MAX_PRODUCT_AMOUNT, 1_000_000),
        ];
    }

    private function castStoredValue(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            default => $value,
        };
    }

    private function encodeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer' => (string) (int) $value,
            default => (string) $value,
        };
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            default => 'string',
        };
    }
}
