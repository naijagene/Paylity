<?php

namespace App\Services\Fulfillment;

use App\Exceptions\VTPassConfigurationException;
use App\Exceptions\VTPassException;
use Illuminate\Support\Facades\Http;

class VTPassService
{
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function pay(array $payload): array
    {
        $this->assertConfigured();

        $response = $this->client()
            ->post($this->endpoint('/api/pay'), $payload);

        return $this->parseResponse($response->json(), 'VTPass payment failed.');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function verifyMeter(array $payload): array
    {
        $this->assertConfigured();

        $response = $this->client()
            ->post($this->endpoint('/api/merchant-verify'), $payload);

        return $this->parseResponse($response->json(), 'VTPass meter verification failed.');
    }

    /**
     * @return array<string, mixed>
     */
    public function queryTransaction(string $requestId): array
    {
        $this->assertConfigured();

        $response = $this->client()
            ->post($this->endpoint('/api/requery'), [
                'request_id' => $requestId,
            ]);

        return $this->parseResponse($response->json(), 'VTPass transaction query failed.');
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    private function parseResponse(?array $payload, string $fallbackMessage): array
    {
        if (! is_array($payload)) {
            throw new VTPassException($fallbackMessage);
        }

        return $payload;
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
        )->withHeaders($headers);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.vtpass.base_url'), '/').$path;
    }
}
