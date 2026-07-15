<?php

namespace App\Services\Marketing;

use App\Models\MarketingEvent;
use App\Models\Transaction;

class MarketingEventService
{
    public const TYPE_VOUCHER_VALIDATED = 'voucher.validated';

    public const TYPE_VOUCHER_RESERVED = 'voucher.reserved';

    public const TYPE_VOUCHER_RELEASED = 'voucher.released';

    public const TYPE_VOUCHER_REDEEMED = 'voucher.redeemed';

    public const TYPE_VOUCHER_BLOCKED = 'voucher.blocked';

    public const TYPE_VOUCHER_REJECTED = 'voucher.rejected';

    public const TYPE_VOUCHER_GENERATED = 'voucher.generated';

    public const TYPE_PAYMENT_COMPLETED = 'payment.completed';

    public const TYPE_FULFILLMENT_COMPLETED = 'fulfillment.completed';

    public const TYPE_REVIEW_SUBMITTED = 'review.submitted';

    public const TYPE_SHARE_INITIATED = 'share.initiated';

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function track(
        string $eventType,
        ?Transaction $transaction = null,
        ?int $launchVoucherId = null,
        ?array $metadata = null,
        string $actor = 'customer',
    ): MarketingEvent {
        return MarketingEvent::query()->create([
            'event_type' => $eventType,
            'reference' => $transaction?->reference,
            'transaction_id' => $transaction?->id,
            'launch_voucher_id' => $launchVoucherId,
            'metadata' => $metadata,
            'actor' => $actor,
            'occurred_at' => now(),
        ]);
    }
}
