<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentAttemptStatus;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;

class FulfillmentAttemptRecorder
{
    public function nextAttemptNumber(Transaction $transaction): int
    {
        return (int) FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->count() + 1;
    }

    public function hasActiveAttempt(Transaction $transaction): bool
    {
        return FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->whereIn('status', FulfillmentAttemptStatus::active())
            ->exists();
    }

    public function hasSuccessfulAttempt(Transaction $transaction): bool
    {
        return FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->where(function ($query) {
                $query
                    ->where('status', FulfillmentAttemptStatus::SUCCEEDED)
                    ->orWhere('outcome', 'success');
            })
            ->exists();
    }

    public function createPending(
        Transaction $transaction,
        string $triggerSource,
        string $actor,
        int $attemptNumber,
        ?string $operatorId = null,
    ): FulfillmentAttempt {
        $requestId = VTPassRequestIdGenerator::forAttempt($transaction, $attemptNumber);
        $now = now();

        return FulfillmentAttempt::query()->create([
            'transaction_id' => $transaction->id,
            'attempt_number' => $attemptNumber,
            'trigger_source' => $triggerSource,
            'provider' => $transaction->fulfillment_provider ?: 'vtpass',
            'request_id' => $requestId,
            'status' => FulfillmentAttemptStatus::PROCESSING,
            'outcome' => 'processing',
            'actor' => $actor,
            'created_by_operator' => $operatorId,
            'started_at' => $now,
            'attempted_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $requestPayload
     */
    public function markSubmitted(FulfillmentAttempt $attempt, array $requestPayload): FulfillmentAttempt
    {
        $attempt->update([
            'status' => FulfillmentAttemptStatus::SUBMITTED,
            'request_payload' => $requestPayload,
            'submitted_at' => now(),
        ]);

        return $attempt->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markSucceeded(
        FulfillmentAttempt $attempt,
        ?array $responsePayload,
        ?string $providerReference,
        ?string $providerCode,
        ?string $providerMessage,
        ?int $durationMs = null,
    ): FulfillmentAttempt {
        $attempt->update([
            'status' => FulfillmentAttemptStatus::SUCCEEDED,
            'outcome' => 'success',
            'response_payload' => $responsePayload,
            'provider_reference' => $providerReference,
            'provider_code' => $providerCode,
            'provider_message' => $providerMessage,
            'duration_ms' => $durationMs,
            'resolved_at' => now(),
            'successful_attempt_key' => 'txn:'.$attempt->transaction_id,
        ]);

        return $attempt->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markConfirmedFailed(
        FulfillmentAttempt $attempt,
        ?array $responsePayload,
        ?string $providerCode,
        ?string $providerMessage,
        ?string $failureReason,
        ?int $durationMs = null,
        ?string $errorClass = null,
        ?string $errorCode = null,
    ): FulfillmentAttempt {
        $attempt->update([
            'status' => FulfillmentAttemptStatus::CONFIRMED_FAILED,
            'outcome' => 'failed',
            'response_payload' => $responsePayload,
            'provider_code' => $providerCode,
            'provider_message' => $providerMessage,
            'failure_reason' => $failureReason,
            'error_class' => $errorClass,
            'error_code' => $errorCode,
            'error_message' => $failureReason,
            'duration_ms' => $durationMs,
            'resolved_at' => now(),
        ]);

        return $attempt->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markUncertain(
        FulfillmentAttempt $attempt,
        ?array $requestPayload = null,
        ?array $responsePayload = null,
        ?string $failureReason = null,
        ?int $durationMs = null,
        ?string $errorClass = null,
        ?string $errorCode = null,
    ): FulfillmentAttempt {
        $attempt->update([
            'status' => FulfillmentAttemptStatus::UNCERTAIN,
            'outcome' => 'uncertain',
            'request_payload' => $requestPayload ?? $attempt->request_payload,
            'response_payload' => $responsePayload,
            'failure_reason' => $failureReason,
            'error_class' => $errorClass,
            'error_code' => $errorCode,
            'error_message' => $failureReason,
            'duration_ms' => $durationMs,
        ]);

        return $attempt->fresh();
    }

    public function markDeadLetter(
        FulfillmentAttempt $attempt,
        string $reason,
        string $actor = 'retry_engine',
    ): FulfillmentAttempt {
        $attempt->update([
            'status' => FulfillmentAttemptStatus::DEAD_LETTER,
            'outcome' => 'dead_letter',
            'failure_reason' => $reason,
            'error_message' => $reason,
            'resolved_at' => now(),
            'actor' => $actor,
        ]);

        return $attempt->fresh();
    }

    /**
     * Legacy post-hoc recorder for backward compatibility in tests.
     *
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
        $attemptNumber = $this->nextAttemptNumber($transaction);
        $status = match ($outcome) {
            'success' => FulfillmentAttemptStatus::SUCCEEDED,
            'dead_letter' => FulfillmentAttemptStatus::DEAD_LETTER,
            'uncertain' => FulfillmentAttemptStatus::UNCERTAIN,
            'error', 'failed' => FulfillmentAttemptStatus::CONFIRMED_FAILED,
            default => FulfillmentAttemptStatus::CONFIRMED_FAILED,
        };

        return FulfillmentAttempt::query()->create([
            'transaction_id' => $transaction->id,
            'attempt_number' => $attemptNumber,
            'provider' => $transaction->fulfillment_provider ?: 'vtpass',
            'request_id' => $requestId ?? VTPassRequestIdGenerator::forAttempt($transaction, $attemptNumber),
            'status' => $status,
            'outcome' => $outcome,
            'duration_ms' => $durationMs,
            'request_payload' => $requestPayload,
            'response_payload' => $responsePayload,
            'failure_reason' => $failureReason,
            'actor' => $actor,
            'attempted_at' => now(),
            'resolved_at' => in_array($status, [
                FulfillmentAttemptStatus::SUCCEEDED,
                FulfillmentAttemptStatus::CONFIRMED_FAILED,
                FulfillmentAttemptStatus::DEAD_LETTER,
            ], true) ? now() : null,
            'successful_attempt_key' => $outcome === 'success' ? 'txn:'.$transaction->id : null,
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
                'trigger_source' => $attempt->trigger_source,
                'provider' => $attempt->provider,
                'request_id' => $attempt->request_id,
                'provider_reference' => $attempt->provider_reference,
                'status' => $attempt->status,
                'outcome' => $attempt->outcome,
                'duration_ms' => $attempt->duration_ms,
                'failure_reason' => $attempt->failure_reason,
                'actor' => $attempt->actor,
                'request_payload' => $attempt->request_payload,
                'response_payload' => $attempt->response_payload,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'resolved_at' => $attempt->resolved_at?->toIso8601String(),
                'attempted_at' => $attempt->attempted_at?->toIso8601String(),
            ])
            ->all();
    }
}
