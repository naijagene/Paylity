<?php

namespace App\Services;

use App\Models\WebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class WebhookEventService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasBeenSuccessfullyProcessed(string $provider, string $eventId): bool
    {
        return WebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->where('status', self::STATUS_PROCESSED)
            ->exists();
    }

    public function find(string $provider, string $eventId): ?WebhookEvent
    {
        return WebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordPending(
        string $provider,
        string $eventId,
        string $eventType,
        ?string $reference,
        array $payload,
    ): WebhookEvent {
        $existing = $this->find($provider, $eventId);

        if ($existing) {
            $existing->update([
                'event_type' => $eventType,
                'reference' => $reference,
                'payload_hash' => hash('sha256', json_encode($payload) ?: ''),
                'status' => self::STATUS_PENDING,
                'failure_reason' => null,
                'payload' => $payload,
            ]);

            return $existing->fresh();
        }

        return WebhookEvent::query()->create([
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'reference' => $reference,
            'payload_hash' => hash('sha256', json_encode($payload) ?: ''),
            'status' => self::STATUS_PENDING,
            'payload' => $payload,
        ]);
    }

    public function markProcessed(int $webhookEventId, ?string $transactionStatus = null): void
    {
        WebhookEvent::query()
            ->whereKey($webhookEventId)
            ->update([
                'status' => self::STATUS_PROCESSED,
                'failure_reason' => null,
                'processed_at' => now(),
            ]);
    }

    public function markProcessedEvent(WebhookEvent $event): void
    {
        $event->update([
            'status' => self::STATUS_PROCESSED,
            'failure_reason' => null,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(int $webhookEventId, string $reason): void
    {
        WebhookEvent::query()
            ->whereKey($webhookEventId)
            ->update([
                'status' => self::STATUS_FAILED,
                'failure_reason' => $reason,
            ]);
    }

    public function markFailedEvent(WebhookEvent $event, string $reason): void
    {
        $event->update([
            'status' => self::STATUS_FAILED,
            'failure_reason' => $reason,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function failedEventsForRetry(int $limit = 25): array
    {
        return WebhookEvent::query()
            ->where('provider', 'paystack')
            ->where('status', self::STATUS_FAILED)
            ->whereNotNull('reference')
            ->orderBy('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (WebhookEvent $event) => [
                'id' => $event->id,
                'reference' => $event->reference,
                'event_id' => $event->event_id,
                'failure_reason' => $event->failure_reason,
            ])
            ->all();
    }

    public function recordDuplicate(string $provider): void
    {
        Cache::increment($this->duplicateCacheKey($provider));
    }

    /**
     * @return array<string, int>
     */
    public function metrics(): array
    {
        $since = Carbon::now()->subDay();

        $processed = (int) WebhookEvent::query()
            ->where('provider', 'paystack')
            ->where('status', self::STATUS_PROCESSED)
            ->where('created_at', '>=', $since)
            ->count();

        $failed = (int) WebhookEvent::query()
            ->where('provider', 'paystack')
            ->where('status', self::STATUS_FAILED)
            ->where('created_at', '>=', $since)
            ->count();

        $pending = (int) WebhookEvent::query()
            ->where('provider', 'paystack')
            ->where('status', self::STATUS_PENDING)
            ->count();

        return [
            'processed_24h' => $processed,
            'failed_24h' => $failed,
            'pending' => $pending,
            'duplicate_24h' => (int) Cache::get($this->duplicateCacheKey('paystack'), 0),
        ];
    }

    private function duplicateCacheKey(string $provider): string
    {
        return 'webhook_metrics:duplicates:'.$provider.':'.Carbon::now()->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasBeenProcessed(string $provider, string $eventId): bool
    {
        return $this->hasBeenSuccessfullyProcessed($provider, $eventId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $provider,
        string $eventId,
        string $eventType,
        ?string $reference,
        array $payload,
        string $status = 'processed',
    ): WebhookEvent {
        if ($status === self::STATUS_PENDING) {
            return $this->recordPending($provider, $eventId, $eventType, $reference, $payload);
        }

        return WebhookEvent::query()->create([
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'reference' => $reference,
            'payload_hash' => hash('sha256', json_encode($payload) ?: ''),
            'status' => $status,
            'payload' => $payload,
            'processed_at' => $status === self::STATUS_PROCESSED ? now() : null,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historyForReference(string $reference): array
    {
        return WebhookEvent::query()
            ->where('reference', $reference)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (WebhookEvent $event) => [
                'provider' => $event->provider,
                'event_id' => $event->event_id,
                'event_type' => $event->event_type,
                'status' => $event->status,
                'failure_reason' => $event->failure_reason,
                'processed_at' => $event->processed_at?->toIso8601String(),
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function purgeProcessedOlderThanDays(int $days): int
    {
        return WebhookEvent::query()
            ->where('status', self::STATUS_PROCESSED)
            ->where('processed_at', '<', now()->subDays($days))
            ->delete();
    }
}
