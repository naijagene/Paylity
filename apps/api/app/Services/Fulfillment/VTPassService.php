<?php

namespace App\Services\Fulfillment;

use App\Exceptions\VTPassConfigurationException;
use App\Exceptions\VTPassException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class VTPassService
{
    public function __construct(
        private readonly VTPassRequestLogger $requestLogger,
        private readonly VTPassResponseMapper $responseMapper,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.vtpass.enabled');
    }

    public function isAutoFulfillEnabled(): bool
    {
        return $this->isEnabled() && (bool) config('services.vtpass.auto_fulfill');
    }

    public function hasCredentials(): bool
    {
        return ! empty(config('services.vtpass.username'))
            && ! empty(config('services.vtpass.password'))
            && ! empty(config('services.vtpass.api_key'));
    }

    public function assertConfigured(): void
    {
        if (! $this->hasCredentials()) {
            throw new VTPassConfigurationException();
        }
    }

    public function isReachable(): bool
    {
        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->get(rtrim((string) config('services.vtpass.base_url'), '/'));

            return $response->successful() || in_array($response->status(), [401, 403, 404, 405], true);
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function pay(array $payload): array
    {
        $this->assertConfigured();

        return $this->sendRequest('pay', '/api/pay', $payload, 'VTPass payment failed.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function verifyMeter(array $payload): array
    {
        $this->assertConfigured();

        return $this->sendRequest(
            'merchant-verify',
            '/api/merchant-verify',
            $payload,
            'VTPass meter verification failed.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function queryTransaction(string $requestId): array
    {
        $this->assertConfigured();

        return $this->sendRequest(
            'requery',
            '/api/requery',
            ['request_id' => $requestId],
            'VTPass transaction query failed.',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendRequest(
        string $endpoint,
        string $path,
        array $payload,
        string $fallbackMessage,
    ): array {
        $startedAt = microtime(true);
        $attempts = max(1, (int) config('services.vtpass.retry_times', 2));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->client()->post($this->endpoint($path), $payload);
                $json = $response->json();

                if (! is_array($json)) {
                    throw new VTPassException($fallbackMessage);
                }

                $mapped = $this->responseMapper->map($json);

                if ($mapped['retryable'] && $attempt < $attempts) {
                    $this->sleepBeforeRetry();

                    continue;
                }

                $this->requestLogger->logCompleted(
                    $endpoint,
                    $payload,
                    $json,
                    (microtime(true) - $startedAt) * 1000,
                );

                return $json;
            } catch (ConnectionException $exception) {
                $lastException = $exception;

                if ($attempt < $attempts) {
                    $this->sleepBeforeRetry();

                    continue;
                }
            } catch (VTPassException $exception) {
                $this->requestLogger->logFailed(
                    $endpoint,
                    $payload,
                    (microtime(true) - $startedAt) * 1000,
                    $exception->getMessage(),
                );

                throw $exception;
            }
        }

        $reason = $lastException?->getMessage() ?? 'VTPass request timed out.';

        $this->requestLogger->logFailed(
            $endpoint,
            $payload,
            (microtime(true) - $startedAt) * 1000,
            $reason,
        );

        throw new VTPassException($reason, 'VTPASS_TIMEOUT');
    }

    private function client()
    {
        $headers = [
            'api-key' => (string) config('services.vtpass.api_key'),
            'Accept' => 'application/json',
        ];

        if ($publicKey = config('services.vtpass.public_key')) {
            $headers['public-key'] = (string) $publicKey;
        }

        return Http::withBasicAuth(
            (string) config('services.vtpass.username'),
            (string) config('services.vtpass.password'),
        )
            ->withHeaders($headers)
            ->timeout($this->timeoutSeconds());
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.vtpass.base_url'), '/').$path;
    }

    private function timeoutSeconds(): int
    {
        return max(5, (int) config('services.vtpass.timeout', 30));
    }

    private function sleepBeforeRetry(): void
    {
        usleep(max(100, (int) config('services.vtpass.retry_sleep_ms', 500)) * 1000);
    }
}
