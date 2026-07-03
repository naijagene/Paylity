<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreLaunchHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.operator.access_key' => self::OPERATOR_KEY]);
    }

    public function test_public_fulfill_route_is_not_available(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260703-PUBLIC',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'verified_phone' => false,
        ]);

        $this->postJson('/api/v1/transactions/'.$transaction->reference.'/fulfill')
            ->assertNotFound();
    }

    public function test_ops_fulfill_still_requires_operator_key(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260703-OPSLOCK',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'verified_phone' => false,
        ]);

        $this->postJson('/api/v1/ops/transactions/'.$transaction->reference.'/fulfill')
            ->assertUnauthorized()
            ->assertJsonPath('errors.code', 'OPERATOR_ACCESS_DENIED');
    }

    public function test_checkout_rate_limit_returns_json_envelope(): void
    {
        $payload = [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'payload' => [],
        ];

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/checkout/initialize', $payload)->assertCreated();
        }

        $this->postJson('/api/v1/checkout/initialize', $payload)
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Too many requests. Please try again shortly.',
                'errors' => ['code' => 'RATE_LIMIT_EXCEEDED'],
            ]);
    }

    public function test_preflight_command_fails_on_critical_production_misconfiguration(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'services.operator.access_key' => null,
        ]);

        $this->artisan('paylity:preflight')
            ->assertExitCode(1);
    }

    public function test_preflight_command_fails_on_staging_debug_enabled(): void
    {
        config([
            'app.env' => 'staging',
            'app.debug' => true,
            'app.url' => 'https://api-staging.paylity.ng',
            'app.frontend_url' => 'https://staging.paylity.ng',
            'app.version' => '1.0.0-rc1',
            'app.build' => '2026.07.03-rc1',
            'services.operator.access_key' => self::OPERATOR_KEY,
        ]);

        $this->artisan('paylity:preflight')
            ->assertExitCode(1);
    }

    public function test_preflight_command_passes_for_valid_staging_configuration(): void
    {
        config([
            'app.env' => 'staging',
            'app.debug' => false,
            'app.url' => 'https://api-staging.paylity.ng',
            'app.frontend_url' => 'https://staging.paylity.ng',
            'app.version' => '1.0.0-rc1',
            'app.build' => '2026.07.03-rc1',
            'services.operator.access_key' => self::OPERATOR_KEY,
            'services.paystack.enabled' => false,
            'services.vtpass.enabled' => false,
        ]);

        $this->artisan('paylity:preflight')
            ->assertExitCode(0);
    }

    public function test_cors_config_includes_frontend_url(): void
    {
        config([
            'app.env' => 'local',
            'app.frontend_url' => 'https://paylity.ng',
        ]);

        $this->assertContains('https://paylity.ng', \App\Support\CorsOriginResolver::allowedOrigins());
        $this->assertContains('http://localhost:3000', \App\Support\CorsOriginResolver::allowedOrigins());
    }

    public function test_production_paystack_errors_are_sanitized(): void
    {
        config([
            'app.debug' => false,
            'services.paystack.enabled' => true,
            'services.paystack.secret_key' => null,
        ]);

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'payload' => [],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Payment provider is temporarily unavailable. Please try again shortly.')
            ->assertJsonPath('errors.code', 'PAYSTACK_NOT_CONFIGURED');
    }
}
