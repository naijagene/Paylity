<?php

namespace App\Services\Fulfillment;

use App\Models\Transaction;
use Illuminate\Support\Str;

class VTPassRequestIdGenerator
{
    public static function forTransaction(Transaction $transaction): string
    {
        $payload = (array) $transaction->request_payload;
        $existing = trim((string) ($payload['request_id'] ?? ''));

        if ($existing !== '') {
            return $existing;
        }

        return self::generate();
    }

    public static function generate(): string
    {
        return now()->format('YmdHis').Str::lower(Str::random(10));
    }
}
