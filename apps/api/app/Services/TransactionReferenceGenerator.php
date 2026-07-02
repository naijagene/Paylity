<?php

namespace App\Services;

class TransactionReferenceGenerator
{
    private const SUFFIX_CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function generate(): string
    {
        $date = now('Africa/Lagos')->format('Ymd');
        $suffix = $this->randomSuffix(6);

        return "PYL-{$date}-{$suffix}";
    }

    private function randomSuffix(int $length): string
    {
        $characters = self::SUFFIX_CHARSET;
        $maxIndex = strlen($characters) - 1;
        $suffix = '';

        for ($i = 0; $i < $length; $i++) {
            $suffix .= $characters[random_int(0, $maxIndex)];
        }

        return $suffix;
    }
}
