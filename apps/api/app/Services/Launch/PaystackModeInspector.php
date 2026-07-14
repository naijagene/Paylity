<?php

namespace App\Services\Launch;

use Illuminate\Support\Facades\Route;

class PaystackModeInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $enabled = (bool) config('services.paystack.enabled');
        $publicKey = (string) config('services.paystack.public_key');
        $secretKey = (string) config('services.paystack.secret_key');
        $callbackUrl = (string) config('services.paystack.callback_url');

        return [
            'enabled' => $enabled,
            'mode' => $this->detectMode($publicKey, $secretKey),
            'callback_url' => $callbackUrl,
            'webhook_route' => '/api/v1/payments/paystack/webhook',
            'webhook_route_exists' => Route::has('api.v1.payments.paystack.webhook')
                || collect(Route::getRoutes())->contains(fn ($route) => in_array('POST', $route->methods(), true)
                    && str_ends_with($route->uri(), 'payments/paystack/webhook')),
            'configuration_complete' => $enabled
                && $publicKey !== ''
                && $secretKey !== ''
                && $callbackUrl !== '',
            'secret_configured' => $secretKey !== '',
            'public_configured' => $publicKey !== '',
        ];
    }

    private function detectMode(string $publicKey, string $secretKey): string
    {
        $keys = [$publicKey, $secretKey];

        if (collect($keys)->contains(fn (string $key) => str_starts_with($key, 'sk_live_') || str_starts_with($key, 'pk_live_'))) {
            return 'live';
        }

        if (collect($keys)->contains(fn (string $key) => str_starts_with($key, 'sk_test_') || str_starts_with($key, 'pk_test_'))) {
            return 'test';
        }

        return 'unknown';
    }
}
