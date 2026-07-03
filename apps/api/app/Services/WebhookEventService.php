<?php

namespace App\Services;

use App\Models\WebhookEvent;

class WebhookEventService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function hasBeenProcessed(string $provider, string $eventId): bool
    {
        return WebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->exists();
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
        return WebhookEvent::query()->create([
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'reference' => $reference,
            'payload_hash' => hash('sha256', json_encode($payload) ?: ''),
            'status' => $status,
            'payload' => $payload,
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
                'created_at' => $event->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
