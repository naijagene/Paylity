<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaylityVtpassCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_vtpass_check_does_not_crash_on_non_json_merchant_verify_response(): void
    {
        $this->configureVtpass([
            'test_disco' => 'IKEDC',
            'test_meter_number' => '45053854956',
        ]);

        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200, ['Content-Type' => 'text/html']),
            'https://sandbox.vtpass.com/api/merchant-verify' => Http::response(
                '<html>Service unavailable</html>',
                502,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-check');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Non-JSON response received from VTPass', $output);
        $this->assertStringContainsString('safe_body_preview=', $output);
        $this->assertStringContainsString('endpoint=merchant-verify', $output);
        $this->assertStringNotContainsString('sandbox-pass', $output);
        $this->assertStringNotContainsString('sandbox-key', $output);
    }

    public function test_vtpass_check_classifies_html_401_as_authentication_failure(): void
    {
        $this->configureVtpass([
            'test_disco' => 'IKEDC',
            'test_meter_number' => '45053854956',
        ]);

        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200),
            'https://sandbox.vtpass.com/api/merchant-verify' => Http::response(
                '<html>Unauthorized</html>',
                401,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-check');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('VTPass authentication failed', $output);
        $this->assertStringContainsString('http_status=401', $output);
        $this->assertStringNotContainsString('Non-JSON response received from VTPass', $output);
    }

    public function test_vtpass_check_reports_authentication_failure_for_json_401_response(): void
    {
        $this->configureVtpass([
            'test_disco' => 'IKEDC',
            'test_meter_number' => '45053854956',
        ]);

        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200),
            'https://sandbox.vtpass.com/api/merchant-verify' => Http::response(
                '',
                401,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-check');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('VTPass authentication failed', $output);
        $this->assertStringContainsString('http_status=401', $output);
        $this->assertStringContainsString('content_type=application/json', $output);
        $this->assertStringContainsString('safe_body_preview=[empty body]', $output);
        $this->assertStringNotContainsString('Non-JSON response received from VTPass', $output);
        $this->assertStringNotContainsString('sandbox-pass', $output);
        $this->assertStringNotContainsString('sandbox-key', $output);
    }

    public function test_vtpass_check_includes_vtpass_message_for_json_401_with_body(): void
    {
        $this->configureVtpass([
            'test_disco' => 'IKEDC',
            'test_meter_number' => '45053854956',
        ]);

        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200),
            'https://sandbox.vtpass.com/api/merchant-verify' => Http::response([
                'message' => 'Invalid authentication credentials',
            ], 401, ['Content-Type' => 'application/json']),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-check');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('VTPass authentication failed', $output);
        $this->assertStringContainsString('vtpass_message=Invalid authentication credentials', $output);
        $this->assertStringNotContainsString('Non-JSON response received from VTPass', $output);
    }

    public function test_vtpass_check_fails_when_merchant_verify_returns_vtpass_error_code(): void
    {
        $this->configureVtpass([
            'test_disco' => 'IKEDC',
            'test_meter_number' => '45053854956',
        ]);

        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200),
            'https://sandbox.vtpass.com/api/merchant-verify' => Http::response([
                'code' => '016',
                'response_description' => 'INVALID CREDENTIALS',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-check');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('FAIL', $output);
        $this->assertStringContainsString('INVALID CREDENTIALS', $output);
        $this->assertStringContainsString('vtpass_code=016', $output);
        $this->assertStringNotContainsString('sandbox-pass', $output);
    }

    public function test_vtpass_check_passes_when_merchant_verify_succeeds(): void
    {
        $this->configureVtpass([
            'test_disco' => 'IKEDC',
            'test_meter_number' => '45053854956',
        ]);

        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200),
            'https://sandbox.vtpass.com/api/merchant-verify' => Http::response([
                'code' => '000',
                'response_description' => 'TRANSACTION SUCCESSFUL',
                'content' => [
                    'Customer_Name' => 'John Doe',
                    'Meter_Number' => '45053854956',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-check');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Verify succeeded', $output);
        $this->assertStringNotContainsString('sandbox-pass', $output);
    }

    public function test_vtpass_check_warns_when_test_meter_values_are_not_configured(): void
    {
        $this->configureVtpass([
            'test_disco' => null,
            'test_meter_number' => null,
        ]);

        Http::fake([
            'https://sandbox.vtpass.com' => Http::response('OK', 200),
        ]);

        $exitCode = Artisan::call('paylity:vtpass-check');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'VTPASS_TEST_DISCO or VTPASS_TEST_METER_NUMBER is not set',
            $output,
        );
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
            'services.vtpass.test_disco' => 'IKEDC',
            'services.vtpass.test_meter_number' => '45053854956',
            'services.vtpass.test_meter_type' => 'prepaid',
            'services.vtpass.retry_times' => 1,
        ];

        $mappedOverrides = [];

        foreach ($overrides as $key => $value) {
            $mappedOverrides['services.vtpass.'.$key] = $value;
        }

        config(array_merge($defaults, $mappedOverrides));
    }
}
