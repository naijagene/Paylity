<?php

namespace App\Services\Launch;

use App\Support\Fulfillment\VTPassEnvironment;

class VtpassModeInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $enabled = (bool) config('services.vtpass.enabled');
        $host = (string) config('services.vtpass.base_url');
        $username = (string) config('services.vtpass.username');
        $password = (string) config('services.vtpass.password');
        $apiKey = (string) config('services.vtpass.api_key');

        return [
            'enabled' => $enabled,
            'mode' => VTPassEnvironment::isProduction() ? 'live' : 'sandbox',
            'host' => $host,
            'auto_fulfill' => (bool) config('services.vtpass.auto_fulfill'),
            'sandbox_tests_disabled' => ! (bool) env('VTPASS_SANDBOX_TESTS', false),
            'configuration_complete' => $enabled
                && $username !== ''
                && $password !== ''
                && $apiKey !== '',
            'credentials_present' => $username !== '' && $password !== '' && $apiKey !== '',
        ];
    }
}
