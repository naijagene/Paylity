<?php

namespace App\Support\Marketing;

use App\Support\PhoneNormalizer;

final class VoucherIdentityNormalizer
{
    public static function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::normalize($phone);
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function hashEmail(?string $email): ?string
    {
        $normalized = self::normalizeEmail((string) $email);

        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, self::pepper());
    }

    public static function hashDevice(?string $deviceId): ?string
    {
        $normalized = trim((string) $deviceId);

        if ($normalized === '') {
            return null;
        }

        return hash_hmac('sha256', $normalized, self::pepper());
    }

    private static function pepper(): string
    {
        return (string) config('app.key', 'paylity-voucher-identity');
    }
}
