<?php

namespace App\Services\Fulfillment;

use App\Support\Fulfillment\VTPassEnvironment;
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
        $this->logSafePayload($endpoint, $payload);

        Log::info('VTPass request completed.', [
            'environment' => VTPassEnvironment::mode(),
            'endpoint' => $endpoint,
            'reference' => $payload['request_id'] ?? null,
            'service' => $payload['serviceID'] ?? null,
            'variation_code' => $this->maskValue($payload['variation_code'] ?? null),
            'billers_code' => $this->maskValue($payload['billersCode'] ?? null),
            'response_code' => is_array($response) ? ($response['code'] ?? null) : null,
            'duration_ms' => (int) round($durationMs),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function logFailed(string $endpoint, array $payload, float $durationMs, string $reason): void
    {
        $this->logSafePayload($endpoint, $payload);

        Log::warning('VTPass request failed.', [
            'environment' => VTPassEnvironment::mode(),
            'endpoint' => $endpoint,
            'reference' => $payload['request_id'] ?? null,
            'service' => $payload['serviceID'] ?? null,
            'variation_code' => $this->maskValue($payload['variation_code'] ?? null),
            'billers_code' => $this->maskValue($payload['billersCode'] ?? null),
            'duration_ms' => (int) round($durationMs),
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function logSafePayload(string $endpoint, array $payload): void
    {
        if ($endpoint !== 'pay' || ! $this->shouldLogDetailedPayload()) {
            return;
        }

        Log::info('VTPass outgoing payload (sanitized).', $this->sanitizePayload($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sanitizePayload(array $payload): array
    {
        $sanitized = $payload;

        foreach (['phone', 'billersCode', 'variation_code', 'meter_number'] as $field) {
            if (array_key_exists($field, $sanitized)) {
                $sanitized[$field] = $this->maskValue($sanitized[$field]);
            }
        }

        unset(
            $sanitized['username'],
            $sanitized['password'],
            $sanitized['api_key'],
            $sanitized['secret_key'],
            $sanitized['public_key'],
        );

        $sanitized['environment'] = VTPassEnvironment::mode();

        return $sanitized;
    }

    public function maskValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $string = (string) $value;
        $length = strlen($string);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($string, 0, 2).str_repeat('*', max(1, $length - 4)).substr($string, -2);
    }

    private function shouldLogDetailedPayload(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        return filter_var(env('VTPASS_SANDBOX_TESTS', false), FILTER_VALIDATE_BOOLEAN)
            || VTPassEnvironment::isProduction();
    }
}
