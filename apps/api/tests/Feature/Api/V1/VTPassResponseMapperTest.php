<?php

namespace Tests\Feature\Api\V1;

use App\Services\Fulfillment\Adapters\ElectricityAdapter;
use App\Services\Fulfillment\ElectricityMeterVerificationService;
use App\Services\Fulfillment\VTPassResponseMapper;
use App\Services\Fulfillment\VTPassService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VTPassResponseMapperTest extends TestCase
{
    public function test_maps_success_code_000(): void
    {
        $mapper = app(VTPassResponseMapper::class);

        $mapped = $mapper->map([
            'code' => '000',
            'response_description' => 'TRANSACTION SUCCESSFUL',
        ]);

        $this->assertSame(VTPassResponseMapper::STATUS_SUCCESS, $mapped['status']);
        $this->assertFalse($mapped['retryable']);
        $this->assertTrue($mapper->isSuccessful(['code' => '000']));
    }

    public function test_maps_failed_code_016(): void
    {
        $mapper = app(VTPassResponseMapper::class);

        $mapped = $mapper->map([
            'code' => '016',
            'response_description' => 'TRANSACTION FAILED',
        ]);

        $this->assertSame(VTPassResponseMapper::STATUS_FAILED, $mapped['status']);
        $this->assertSame('TRANSACTION FAILED', $mapper->failureReason([
            'code' => '016',
            'response_description' => 'TRANSACTION FAILED',
        ]));
    }

    public function test_failure_reason_prefers_nested_content_error_over_generic_description(): void
    {
        $mapper = app(VTPassResponseMapper::class);

        $response = [
            'code' => '016',
            'response_description' => 'TRANSACTION FAILED',
            'content' => [
                'error' => 'VARIATION CODE DOES NOT EXIST FOR SELECTED PRODUCT',
            ],
        ];

        $this->assertSame(
            'VARIATION CODE DOES NOT EXIST FOR SELECTED PRODUCT',
            $mapper->failureReason($response),
        );
        $this->assertSame(
            'VARIATION CODE DOES NOT EXIST FOR SELECTED PRODUCT',
            $mapper->map($response)['message'],
        );
    }

    public function test_failure_reason_combines_generic_description_with_nested_detail_when_both_specific(): void
    {
        $mapper = app(VTPassResponseMapper::class);

        $response = [
            'code' => '016',
            'response_description' => 'INSUFFICIENT WALLET BALANCE',
            'content' => [
                'error' => 'Kindly fund your wallet to continue',
            ],
        ];

        $this->assertSame(
            'INSUFFICIENT WALLET BALANCE — Kindly fund your wallet to continue',
            $mapper->failureReason($response),
        );
    }

    public function test_maps_retryable_code_030(): void
    {
        $mapper = app(VTPassResponseMapper::class);

        $mapped = $mapper->map([
            'code' => '030',
            'response_description' => 'SERVICE TEMPORARILY UNAVAILABLE',
        ]);

        $this->assertSame(VTPassResponseMapper::STATUS_RETRYABLE, $mapped['status']);
        $this->assertTrue($mapped['retryable']);
    }

    public function test_meter_verification_unavailable_when_feature_disabled(): void
    {
        config(['services.vtpass.enabled' => false]);

        $result = app(ElectricityMeterVerificationService::class)->verify(
            'IKEDC',
            '45053854956',
            'prepaid',
        );

        $this->assertFalse($result['available']);
        $this->assertFalse($result['verified']);
        $this->assertStringContainsString('FEATURE_VTPASS=false', $result['message']);
    }

    public function test_meter_verification_unavailable_without_credentials(): void
    {
        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.username' => null,
            'services.vtpass.password' => null,
            'services.vtpass.api_key' => null,
        ]);

        $result = app(ElectricityMeterVerificationService::class)->verify(
            'IKEDC',
            '45053854956',
            'prepaid',
        );

        $this->assertFalse($result['available']);
        $this->assertStringContainsString('credentials are not configured', $result['message']);
    }

    public function test_authentication_failure_for_json_401_includes_safe_context(): void
    {
        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.username' => 'sandbox-user',
            'services.vtpass.password' => 'sandbox-pass',
            'services.vtpass.api_key' => 'sandbox-key',
            'services.vtpass.retry_times' => 1,
        ]);

        Http::fake([
            'https://sandbox.vtpass.com/api/merchant-verify' => Http::response(
                '{"message":"Unauthorized"}',
                401,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        try {
            app(VTPassService::class)->verifyMeter(
                app(ElectricityAdapter::class)->buildVerifyPayload('IKEDC', '45053854956', 'prepaid'),
            );
            $this->fail('Expected VTPassException was not thrown.');
        } catch (\App\Exceptions\VTPassException $exception) {
            $this->assertSame('VTPASS_AUTH_FAILED', $exception->errorCode);
            $this->assertStringContainsString('VTPass authentication failed', $exception->getMessage());
            $this->assertSame(401, $exception->safeContext()['http_status']);
            $this->assertStringContainsString('application/json', (string) $exception->safeContext()['content_type']);
            $this->assertSame('Unauthorized', $exception->safeContext()['vtpass_message']);
        }
    }

    public function test_non_json_response_includes_safe_context(): void
    {
        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.username' => 'sandbox-user',
            'services.vtpass.password' => 'sandbox-pass',
            'services.vtpass.api_key' => 'sandbox-key',
            'services.vtpass.retry_times' => 1,
        ]);

        Http::fake([
            'https://sandbox.vtpass.com/api/merchant-verify' => Http::response(
                'not-json',
                502,
                ['Content-Type' => 'text/plain'],
            ),
        ]);

        try {
            app(VTPassService::class)->verifyMeter(
                app(ElectricityAdapter::class)->buildVerifyPayload('IKEDC', '45053854956', 'prepaid'),
            );
            $this->fail('Expected VTPassException was not thrown.');
        } catch (\App\Exceptions\VTPassException $exception) {
            $this->assertSame('VTPASS_NON_JSON_RESPONSE', $exception->errorCode);
            $this->assertStringContainsString(
                'Non-JSON response received from VTPass',
                $exception->getMessage(),
            );
            $this->assertSame('merchant-verify', $exception->safeContext()['endpoint']);
            $this->assertSame(502, $exception->safeContext()['http_status']);
            $this->assertSame('text/plain', $exception->safeContext()['content_type']);
        }
    }

    public function test_empty_meter_is_rejected_before_vtpass_api_call(): void
    {
        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.username' => 'sandbox-user',
            'services.vtpass.password' => 'sandbox-pass',
            'services.vtpass.api_key' => 'sandbox-key',
        ]);

        Http::fake();

        $result = app(ElectricityMeterVerificationService::class)->verify(
            'IKEDC',
            '',
            'prepaid',
        );

        $this->assertFalse($result['verified']);
        $this->assertSame(VTPassResponseMapper::STATUS_FAILED, $result['status']);
        $this->assertStringContainsString('Meter number is required', $result['message']);

        Http::assertNothingSent();
    }

    public function test_timeout_handling_returns_vtpass_timeout(): void
    {
        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.username' => 'sandbox-user',
            'services.vtpass.password' => 'sandbox-pass',
            'services.vtpass.api_key' => 'sandbox-key',
            'services.vtpass.retry_times' => 1,
            'services.vtpass.timeout' => 1,
        ]);

        Http::fake(function () {
            throw new ConnectionException('Connection timed out.');
        });

        $this->expectException(\App\Exceptions\VTPassException::class);
        $this->expectExceptionMessage('Connection timed out.');

        app(VTPassService::class)->verifyMeter(
            app(ElectricityAdapter::class)->buildVerifyPayload('IKEDC', '45053854956', 'prepaid'),
        );
    }
}
