<?php

namespace App\Enums;

class TransactionStatus
{
    public const CREATED = 'created';

    public const VALIDATED = 'validated';

    public const PAYMENT_PENDING = 'payment_pending';

    public const PAYMENT_SUCCESS = 'payment_success';

    public const PAYMENT_FAILED = 'payment_failed';

    public const FULFILLMENT_PENDING = 'fulfillment_pending';

    public const FULFILLED = 'fulfilled';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::CREATED,
            self::VALIDATED,
            self::PAYMENT_PENDING,
            self::PAYMENT_SUCCESS,
            self::PAYMENT_FAILED,
            self::FULFILLMENT_PENDING,
            self::FULFILLED,
            self::FAILED,
            self::CANCELLED,
        ];
    }
}
