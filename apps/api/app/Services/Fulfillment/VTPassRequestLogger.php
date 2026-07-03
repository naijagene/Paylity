<?php

namespace App\Services\Fulfillment;

use Illuminate\Support\Facades\Log;

class VTPassRequestLogger
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $response
     */
    public function logCompleted(
        string $endpoint,
        array $payload,
        ?array $response,
        float $durationMs,
    ): void {
        $this->logSafeDataPurchasePayload($endpoint, $payload);

        Log::info('VTPass request completed.', [
            'endpoint' => $endpoint,
            'reference' => $payload['request_id'] ?? null,
            'service' => $payload['serviceID'] ?? null,
            'response_code' => is_array($response) ? ($response['code'] ?? null) : null,
            'duration_ms' => (int) round($durationMs),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function logFailed(string $endpoint, array $payload, float $durationMs, string $reason): void
    {
        $this->logSafeDataPurchasePayload($endpoint, $payload);

        Log::warning('VTPass request failed.', [
            'endpoint' => $endpoint,
            'reference' => $payload['request_id'] ?? null,
            'service' => $payload['serviceID'] ?? null,
            'duration_ms' => (int) round($durationMs),
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function logSafeDataPurchasePayload(string $endpoint, array $payload): void
    {
        if ($endpoint !== 'pay' || ! $this->shouldLogDataPurchasePayload()) {
            return;
        }

        $serviceId = strtolower((string) ($payload['serviceID'] ?? ''));

        if (! str_ends_with($serviceId, '-data')) {
            return;
        }

        $safePayload = \App\Services\Fulfillment\Adapters\DataAdapter::sanitizeForDiagnostics($payload);

        Log::info('VTPass data purchase outgoing payload (sanitized).', $safePayload);
    }

    private function shouldLogDataPurchasePayload(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        return filter_var(env('VTPASS_SANDBOX_TESTS', false), FILTER_VALIDATE_BOOLEAN);
    }
}
