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
        Log::warning('VTPass request failed.', [
            'endpoint' => $endpoint,
            'reference' => $payload['request_id'] ?? null,
            'service' => $payload['serviceID'] ?? null,
            'duration_ms' => (int) round($durationMs),
            'reason' => $reason,
        ]);
    }
}
