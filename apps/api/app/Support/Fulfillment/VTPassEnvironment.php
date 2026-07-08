<?php

namespace App\Support\Fulfillment;

final class VTPassEnvironment
{
    public const SANDBOX = 'sandbox';

    public const PRODUCTION = 'production';

    public const SANDBOX_BASE_URL = 'https://sandbox.vtpass.com';

    public const PRODUCTION_BASE_URL = 'https://vtpass.com';

    public static function mode(): string
    {
        $mode = strtolower((string) config('services.vtpass.environment', self::SANDBOX));

        return in_array($mode, [self::SANDBOX, self::PRODUCTION], true)
            ? $mode
            : self::SANDBOX;
    }

    public static function isSandbox(): bool
    {
        return self::mode() === self::SANDBOX;
    }

    public static function isProduction(): bool
    {
        return self::mode() === self::PRODUCTION;
    }

    public static function defaultBaseUrl(string $mode): string
    {
        return $mode === self::PRODUCTION
            ? self::PRODUCTION_BASE_URL
            : self::SANDBOX_BASE_URL;
    }

    public static function configuredBaseUrl(): string
    {
        return rtrim((string) config('services.vtpass.base_url', self::defaultBaseUrl(self::mode())), '/');
    }

    public static function baseUrlHost(): string
    {
        $host = parse_url(self::configuredBaseUrl(), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'unknown';
    }

    public static function baseUrlMatchesMode(): bool
    {
        $baseUrl = strtolower(self::configuredBaseUrl());
        $isSandboxUrl = str_contains($baseUrl, 'sandbox.vtpass.com');
        $isProductionUrl = str_contains($baseUrl, 'vtpass.com') && ! $isSandboxUrl;

        return self::isSandbox() ? $isSandboxUrl : $isProductionUrl;
    }
}
