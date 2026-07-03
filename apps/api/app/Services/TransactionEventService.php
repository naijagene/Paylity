<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionEvent;
use Illuminate\Support\Collection;

class TransactionEventService
{
    public const TYPE_CREATED = 'transaction.created';

    public const TYPE_PAYMENT_PENDING = 'payment.pending';

    public const TYPE_PAYMENT_SUCCESS = 'payment.success';

    public const TYPE_PAYMENT_FAILED = 'payment.failed';

    public const TYPE_FULFILLMENT_PENDING = 'fulfillment.pending';

    public const TYPE_FULFILLED = 'fulfillment.fulfilled';

    public const TYPE_FULFILLMENT_FAILED = 'fulfillment.failed';

    public const TYPE_FULFILLMENT_RETRY = 'fulfillment.retry';

    public const TYPE_WEBHOOK_RECEIVED = 'webhook.received';

    public const TYPE_OPS_FULFILL = 'ops.fulfill';

    public const TYPE_OPS_NOTE = 'ops.note';

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        Transaction $transaction,
        string $eventType,
        string $summary,
        string $actor = 'system',
        ?array $metadata = null,
    ): TransactionEvent {
        return TransactionEvent::query()->create([
            'transaction_id' => $transaction->id,
            'event_type' => $eventType,
            'actor' => $actor,
            'summary' => $summary,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function timelineFor(Transaction $transaction): Collection
    {
        return TransactionEvent::query()
            ->where('transaction_id', $transaction->id)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (TransactionEvent $event) => [
                'event_type' => $event->event_type,
                'actor' => $event->actor,
                'summary' => $event->summary,
                'metadata' => $event->metadata,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ]);
    }
}
