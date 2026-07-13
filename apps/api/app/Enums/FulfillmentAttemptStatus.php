<?php

namespace App\Enums;

final class FulfillmentAttemptStatus
{
    public const CREATED = 'created';

    public const PROCESSING = 'processing';

    public const SUBMITTED = 'submitted';

    public const SUCCEEDED = 'succeeded';

    public const CONFIRMED_FAILED = 'confirmed_failed';

    public const UNCERTAIN = 'uncertain';

    public const RETRY_SCHEDULED = 'retry_scheduled';

    public const DEAD_LETTER = 'dead_letter';

    public const CANCELLED = 'cancelled';

    /**
     * @return list<string>
     */
    public static function active(): array
    {
        return [
            self::CREATED,
            self::PROCESSING,
            self::SUBMITTED,
            self::UNCERTAIN,
        ];
    }
}
