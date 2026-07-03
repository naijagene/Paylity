<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaylityVtpassVariationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_vtpass_variations_command_outputs_variation_rows_on_success(): void
    {
        $this->configureVtpass();

        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200),
            'https://sandbox.vtpass.com/api/service-variations*' => Http::response([
                'code' => '000',
                'response_description' => '000',
                'content' => [
                    'serviceID' => 'mtn-data',
                    'variations' => [
                        [
                            'variation_code' => 'mtn-50mb',
                            'name' => '50MB',
                            'variation_amount' => '50.00',
                            'fixedPrice' => 'Yes',
                        ],
                        [
                            'variation_code' => 'mtn-100mb',
                            'name' => '100MB',
                            'variation_amount' => '100.00',
                            'fixedPrice' => 'No',
                        ],
                    ],
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-variations', ['serviceId' => 'mtn-data']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('variation_code', $output);
        $this->assertStringContainsString('mtn-50mb', $output);
        $this->assertStringContainsString('50MB', $output);
        $this->assertStringContainsString('50.00', $output);
        $this->assertStringContainsString('Yes', $output);
        $this->assertStringContainsString('VTPASS_TEST_DATA_VARIATION_CODE', $output);
        $this->assertStringNotContainsString('sandbox-pass', $output);
        $this->assertStringNotContainsString('sandbox-key', $output);
    }

    public function test_vtpass_variations_command_reports_authentication_failure_safely(): void
    {
        $this->configureVtpass();

        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200),
            'https://sandbox.vtpass.com/api/service-variations*' => Http::response([
                'message' => 'Invalid authentication credentials',
            ], 401, ['Content-Type' => 'application/json']),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-variations', ['serviceId' => 'mtn-data']);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('VTPass authentication failed', $output);
        $this->assertStringContainsString('http_status=401', $output);
        $this->assertStringContainsString('endpoint=service-variations', $output);
        $this->assertStringNotContainsString('sandbox-pass', $output);
        $this->assertStringNotContainsString('sandbox-key', $output);
    }

    public function test_vtpass_variations_command_reports_non_json_failure_safely(): void
    {
        $this->configureVtpass();

        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200),
            'https://sandbox.vtpass.com/api/service-variations*' => Http::response(
                '<html>Service unavailable</html>',
                502,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-variations', ['serviceId' => 'mtn-data']);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Non-JSON response received from VTPass', $output);
        $this->assertStringContainsString('safe_body_preview=', $output);
        $this->assertStringContainsString('endpoint=service-variations', $output);
        $this->assertStringNotContainsString('sandbox-pass', $output);
        $this->assertStringNotContainsString('sandbox-key', $output);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function configureVtpass(array $overrides = []): void
    {
        $defaults = [
            'services.vtpass.enabled' => true,
            'services.vtpass.username' => 'sandbox-user',
            'services.vtpass.password' => 'sandbox-pass',
            'services.vtpass.api_key' => 'sandbox-key',
            'services.vtpass.base_url' => 'https://sandbox.vtpass.com',
            'services.vtpass.retry_times' => 1,
        ];

        $mappedOverrides = [];

        foreach ($overrides as $key => $value) {
            $mappedOverrides['services.vtpass.'.$key] = $value;
        }

        config(array_merge($defaults, $mappedOverrides));
    }
}
