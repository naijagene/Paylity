<?php

namespace Tests\Unit\Services\Fulfillment;

use App\Services\Fulfillment\VTPassRequestLogger;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class VTPassRequestLoggerTest extends TestCase
{
    public function test_sanitize_payload_masks_sensitive_fields_and_omits_credentials(): void
    {
        config(['services.vtpass.environment' => 'production']);

        $logger = app(VTPassRequestLogger::class);

        $sanitized = $logger->sanitizePayload([
            'request_id' => 'REQ-123456',
            'serviceID' => 'mtn-data',
            'billersCode' => '08031234567',
            'variation_code' => 'mtn-1gb-daily',
            'phone' => '08031234567',
            'username' => 'secret-user',
            'password' => 'secret-pass',
            'api_key' => 'secret-key',
        ]);

        $this->assertSame('production', $sanitized['environment']);
        $this->assertSame('REQ-123456', $sanitized['request_id']);
        $this->assertSame('mtn-data', $sanitized['serviceID']);
        $this->assertStringContainsString('*', (string) $sanitized['billersCode']);
        $this->assertStringNotContainsString('08031234567', (string) $sanitized['billersCode']);
        $this->assertArrayNotHasKey('username', $sanitized);
        $this->assertArrayNotHasKey('password', $sanitized);
        $this->assertArrayNotHasKey('api_key', $sanitized);
    }

    public function test_completed_log_does_not_include_credentials(): void
    {
        Log::spy();

        config(['services.vtpass.environment' => 'sandbox']);

        $logger = app(VTPassRequestLogger::class);
        $logger->logCompleted('pay', [
            'request_id' => 'REQ-LOG-1',
            'serviceID' => 'mtn',
            'billersCode' => '08031234567',
            'variation_code' => 'vt-100',
        ], ['code' => '000'], 120.5);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'VTPass request completed.'
                    && $context['reference'] === 'REQ-LOG-1'
                    && $context['environment'] === 'sandbox'
                    && ! isset($context['password'])
                    && ! isset($context['api_key']);
            });
    }
}
