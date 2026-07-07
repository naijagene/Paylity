<?php

namespace App\Services\Fulfillment;

use App\Exceptions\VTPassConfigurationException;
use App\Exceptions\VTPassException;
use App\Services\Platform\FeatureFlagService;
use App\Support\Platform\FeatureFlagKeys;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class VTPassService
{
    /** @var array<string, mixed>|null */
    private ?array $lastRequestDiagnostics = null;

    public function __construct(
        private readonly VTPassRequestLogger $requestLogger,
        private readonly VTPassResponseMapper $responseMapper,
        private readonly FeatureFlagService $featureFlags,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->featureFlags->isEnabled(
            FeatureFlagKeys::VTPASS,
            (bool) config('services.vtpass.enabled'),
        );
    }

    public function isAutoFulfillEnabled(): bool
    {
        return $this->isEnabled() && $this->featureFlags->isEnabled(
            FeatureFlagKeys::VTPASS_AUTO_FULFILL,
            (bool) config('services.vtpass.auto_fulfill'),
        );
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

    /**
     * @return array<string, mixed>|null
     */
    public function lastRequestDiagnostics(): ?array
    {
        return $this->lastRequestDiagnostics;
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
     * @return array<string, mixed>
     */
    public function getServiceVariations(string $serviceId): array
    {
        $this->assertConfigured();

        return $this->sendRequest(
            'service-variations',
            '/api/service-variations',
            ['serviceID' => $serviceId],
            'VTPass service variations request failed.',
            'get',
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
        string $method = 'post',
    ): array {
        $startedAt = microtime(true);
        $attempts = max(1, (int) config('services.vtpass.retry_times', 2));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $client = $this->client();
                $url = $this->endpoint($path);
                $response = $method === 'get'
                    ? $client->get($url, $payload)
                    : $client->post($url, $payload);
                $diagnostics = $this->buildDiagnostics($endpoint, $response);
                $body = (string) $response->body();
                $json = $response->json();

                if ($response->status() === 401) {
                    throw $this->buildAuthenticationFailureException($diagnostics, $json, $body);
                }

                if (! is_array($json)) {
                    throw $this->buildInvalidResponseException($diagnostics, $body);
                }

                $mapped = $this->responseMapper->map($json);
                $diagnostics['vtpass_code'] = $mapped['code'];
                $diagnostics['vtpass_message'] = $mapped['message'];
                $this->lastRequestDiagnostics = $diagnostics;

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
                $this->lastRequestDiagnostics = [
                    'endpoint' => $endpoint,
                    'http_status' => null,
                    'content_type' => null,
                    'vtpass_message' => $exception->getMessage(),
                ];

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

        throw new VTPassException($reason, 'VTPASS_TIMEOUT', [
            'endpoint' => $endpoint,
            'http_status' => $this->lastRequestDiagnostics['http_status'] ?? null,
            'content_type' => $this->lastRequestDiagnostics['content_type'] ?? null,
            'vtpass_message' => $reason,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDiagnostics(string $endpoint, Response $response): array
    {
        return [
            'endpoint' => $endpoint,
            'http_status' => $response->status(),
            'content_type' => $response->header('Content-Type') ?? 'unknown',
        ];
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     * @param  array<string, mixed>|null  $json
     */
    private function buildAuthenticationFailureException(
        array $diagnostics,
        ?array $json,
        string $body,
    ): VTPassException {
        $diagnostics['vtpass_message'] = $this->resolveVTPassMessage($json, $body);

        if ($diagnostics['vtpass_message'] === null) {
            $diagnostics['safe_body_preview'] = $this->safeBodyPreview($body);
        }

        return new VTPassException(
            'VTPass authentication failed. Check sandbox username, password, API key, and whether the sandbox account is active.',
            'VTPASS_AUTH_FAILED',
            $diagnostics,
        );
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     */
    private function buildInvalidResponseException(array $diagnostics, string $body): VTPassException
    {
        $contentType = (string) ($diagnostics['content_type'] ?? 'unknown');

        if ($this->isJsonContentType($contentType)) {
            $diagnostics['safe_body_preview'] = $this->safeBodyPreview($body);

            return new VTPassException(
                'Unable to parse JSON response from VTPass. Check credentials, endpoint, or sandbox availability.',
                'VTPASS_INVALID_JSON_RESPONSE',
                $diagnostics,
            );
        }

        $diagnostics['safe_body_preview'] = $this->safeBodyPreview($body);

        return new VTPassException(
            'Non-JSON response received from VTPass. Check credentials, endpoint, or sandbox availability.',
            'VTPASS_NON_JSON_RESPONSE',
            $diagnostics,
        );
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function resolveVTPassMessage(?array $json, string $body): ?string
    {
        if (is_array($json)) {
            $message = data_get($json, 'response_description')
                ?? data_get($json, 'message')
                ?? data_get($json, 'error');

            if ($message !== null && $message !== '') {
                return (string) $message;
            }
        }

        return null;
    }

    private function isJsonContentType(string $contentType): bool
    {
        $normalized = strtolower($contentType);

        return str_contains($normalized, 'application/json')
            || str_contains($normalized, '+json');
    }

    private function safeBodyPreview(string $body): string
    {
        $preview = trim(preg_replace('/\s+/', ' ', $body) ?? '');

        if ($preview === '') {
            return '[empty body]';
        }

        $preview = mb_substr($preview, 0, 200);

        foreach ([
            (string) config('services.vtpass.username'),
            (string) config('services.vtpass.password'),
            (string) config('services.vtpass.api_key'),
            (string) config('services.vtpass.secret_key'),
        ] as $secret) {
            if ($secret !== '') {
                $preview = str_replace($secret, '[redacted]', $preview);
            }
        }

        return $preview;
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
