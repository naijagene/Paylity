<?php

namespace App\Enums;

final class FulfillmentTriggerSource
{
    public const WEBHOOK = 'webhook';

    public const CALLBACK = 'callback';

    public const SCHEDULER = 'scheduler';

    public const AUTOMATIC_RETRY = 'automatic_retry';

    public const MANUAL_RETRY = 'manual_retry';

    public const RECONCILIATION = 'reconciliation';

    public const OPERATOR = 'operator';
}
