<?php

namespace App\Support;

final class PhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '234') && strlen($digits) >= 13) {
            return '0'.substr($digits, 3);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return $digits;
        }

        if (strlen($digits) === 10) {
            return '0'.$digits;
        }

        return $digits;
    }

    public static function isValidNigerianPhone(string $phone): bool
    {
        $normalized = self::normalize($phone);

        return (bool) preg_match('/^0[789][01]\d{8}$/', $normalized);
    }

    public static function mask(string $phone): string
    {
        $normalized = self::normalize($phone);

        if (strlen($normalized) < 7) {
            return $normalized;
        }

        return substr($normalized, 0, 4).'****'.substr($normalized, -3);
    }
}
