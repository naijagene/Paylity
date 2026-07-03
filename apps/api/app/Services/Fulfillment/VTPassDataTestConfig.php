<?php

namespace App\Services\Fulfillment;

class VTPassDataTestConfig
{
    public static function phone(): string
    {
        return trim((string) config('services.vtpass.test_data_phone', '')) ?: '08011111111';
    }

    public static function billersCode(): string
    {
        $preferred = trim((string) config('services.vtpass.test_data_billers_code', ''));
        if ($preferred !== '') {
            return $preferred;
        }

        return self::phone();
    }

    public static function serviceId(): string
    {
        return trim((string) config('services.vtpass.test_data_service_id', ''));
    }

    public static function variationCode(): string
    {
        return trim((string) config('services.vtpass.test_data_variation_code', ''));
    }
}
