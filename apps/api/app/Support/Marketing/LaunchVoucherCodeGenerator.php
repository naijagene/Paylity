<?php

namespace App\Support\Marketing;

use App\Models\LaunchVoucher;

class LaunchVoucherCodeGenerator
{
    /** Excludes 0, O, 1, I, L for readability. 32^8 ≈ 1.1T combinations (>40 bits). */
    private const CHARSET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const PREFIX = 'PYL';

    private const LEGACY_BLOCKED_CODES = [
        'PAYLITY500',
        'PAYLITY1000',
        'SOFT500',
        'SOFT1000',
        'WELCOME500',
    ];

    public function generateUnique(int $maxAttempts = 25): string
    {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = $this->generate();
            $normalized = $this->normalize($code);

            if (in_array($normalized, self::LEGACY_BLOCKED_CODES, true)) {
                continue;
            }

            if (! LaunchVoucher::query()->where('code_normalized', $normalized)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate a unique launch voucher code.');
    }

    public function generate(): string
    {
        return self::PREFIX.'-'.$this->randomSegment(4).'-'.$this->randomSegment(4);
    }

    public function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/', '', trim($code)));
    }

    public function formatForDisplay(string $code): string
    {
        $normalized = $this->normalize($code);

        if (str_starts_with($normalized, self::PREFIX) && strlen($normalized) === 11) {
            return self::PREFIX.'-'.substr($normalized, 3, 4).'-'.substr($normalized, 7, 4);
        }

        return strtoupper(trim($code));
    }

    public function mask(string $code): string
    {
        $normalized = $this->normalize($code);

        return '••••'.substr($normalized, -4);
    }

    public function isValidFormat(string $code): bool
    {
        $normalized = $this->normalize($code);

        if (strlen($normalized) !== 11 || ! str_starts_with($normalized, self::PREFIX)) {
            return false;
        }

        $suffix = substr($normalized, 3);

        return (bool) preg_match('/^['.self::CHARSET.']{8}$/', $suffix);
    }

    private function randomSegment(int $length): string
    {
        $charset = self::CHARSET;
        $maxIndex = strlen($charset) - 1;
        $segment = '';

        for ($index = 0; $index < $length; $index++) {
            $segment .= $charset[random_int(0, $maxIndex)];
        }

        return $segment;
    }
}
