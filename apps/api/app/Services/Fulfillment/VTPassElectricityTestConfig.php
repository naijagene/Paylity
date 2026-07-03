<?php

namespace App\Services\Fulfillment;

class VTPassElectricityTestConfig
{
    public static function disco(): string
    {
        $preferred = trim((string) config('services.vtpass.test_electricity_disco', ''));
        if ($preferred !== '') {
            return $preferred;
        }

        $legacy = trim((string) config('services.vtpass.test_disco', ''));

        return $legacy !== '' ? $legacy : 'IKEDC';
    }

    public static function meterNumber(): string
    {
        $preferred = trim((string) config('services.vtpass.test_electricity_meter_number', ''));
        if ($preferred !== '') {
            return $preferred;
        }

        return trim((string) config('services.vtpass.test_meter_number', ''));
    }

    public static function meterType(): string
    {
        $preferred = trim((string) config('services.vtpass.test_electricity_meter_type', ''));
        if ($preferred !== '') {
            return strtolower($preferred) ?: 'prepaid';
        }

        $legacy = trim((string) config('services.vtpass.test_meter_type', 'prepaid'));

        return strtolower($legacy) ?: 'prepaid';
    }

    public static function isConfigured(): bool
    {
        return self::meterNumber() !== '';
    }

    public static function missingConfigMessage(): string
    {
        return 'Set VTPASS_TEST_ELECTRICITY_METER_NUMBER (or legacy VTPASS_TEST_METER_NUMBER) for sandbox merchant verify.';
    }
}
