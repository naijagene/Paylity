<?php

namespace App\Services\Fulfillment;

use App\Models\FulfillmentAttempt;
use App\Models\Transaction;

class FulfillmentAttemptRecorder
{
    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function record(
        Transaction $transaction,
        string $outcome,
        string $actor = 'system',
        ?string $requestId = null,
        ?array $requestPayload = null,
        ?array $responsePayload = null,
        ?string $failureReason = null,
        ?int $durationMs = null,
    ): FulfillmentAttempt {
        $attemptNumber = (int) FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->count() + 1;

        return FulfillmentAttempt::query()->create([
            'transaction_id' => $transaction->id,
            'attempt_number' => $attemptNumber,
            'provider' => $transaction->fulfillment_provider ?: 'vtpass',
            'request_id' => $requestId,
            'outcome' => $outcome,
            'duration_ms' => $durationMs,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'failure_reason' => $failureReason,
            'actor' => $actor,
            'attempted_at' => now(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function historyFor(Transaction $transaction): array
    {
        return FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->orderBy('attempted_at')
            ->get()
            ->map(fn (FulfillmentAttempt $attempt) => [
                'attempt_number' => $attempt->attempt_number,
                'provider' => $attempt->provider,
                'request_id' => $attempt->request_id,
                'outcome' => $attempt->outcome,
                'duration_ms' => $attempt->duration_ms,
                'failure_reason' => $attempt->failure_reason,
                'actor' => $attempt->actor,
                'request_payload' => $attempt->request_payload,
                'response_payload' => $attempt->response_payload,
                'attempted_at' => $attempt->attempted_at?->toIso8601String(),
            ])
            ->all();
    }
}
