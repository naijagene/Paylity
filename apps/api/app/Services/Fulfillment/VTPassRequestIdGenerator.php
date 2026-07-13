<?php

namespace App\Services\Fulfillment;

use App\Models\Transaction;

/**
 * Auditable VTPass request IDs — one per logical fulfillment attempt.
 *
 * Format: {PAYLITY_REFERENCE}-F{attempt_number}
 * Example: PYL-20260710-ABC123-F01
 *
 * Rules:
 * - Attempt number is zero-padded to 2 digits (supports up to 99 attempts).
 * - Retain the same ID when reconciling an uncertain provider outcome.
 * - Generate a new ID only for a new logical attempt after confirmed failure.
 */
class VTPassRequestIdGenerator
{
    public static function forAttempt(Transaction $transaction, int $attemptNumber): string
    {
        return sprintf('%s-F%02d', $transaction->reference, max(1, $attemptNumber));
    }

    /**
     * @deprecated Use forAttempt() — checkout payload IDs are no longer reused across attempts.
     */
    public static function forTransaction(Transaction $transaction): string
    {
        return self::forAttempt($transaction, 1);
    }

    public static function generate(): string
    {
        return 'PYL-'.now()->format('YmdHis').strtolower(substr(bin2hex(random_bytes(5)), 0, 10));
    }
}
