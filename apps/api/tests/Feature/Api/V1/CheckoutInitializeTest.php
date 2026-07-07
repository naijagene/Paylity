<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsProductCatalog;
use Tests\Concerns\SeedsPlatformSettings;
use Tests\TestCase;

class CheckoutInitializeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;
    use SeedsPlatformSettings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProductCatalog();
        $this->seedPlatformSettings();

        config([
            'services.paystack.enabled' => false,
            'services.paystack.secret_key' => null,
        ]);
    }

    public function test_checkout_initialize_works_for_max_guest_product_amount(): void
    {
        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'customer_email' => 'user@example.com',
            'product_amount' => 10_000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Checkout initialized successfully.',
                'data' => [
                    'product_type' => 'airtime',
                    'product_amount' => 10_000,
                    'convenience_fee' => 100,
                    'gateway_fee' => 0,
                    'payable_amount' => 10_100,
                    'currency' => 'NGN',
                    'status' => 'created',
                    'payment_status' => 'payment integration coming next',
                ],
            ]);

        $this->assertDatabaseHas('transactions', [
            'product_amount' => 10_000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 10_100,
            'status' => 'created',
        ]);
    }

    public function test_payable_amount_can_exceed_guest_product_limit_because_fees_are_separate(): void
    {
        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 10_000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ]);

        $response->assertCreated();

        $this->assertSame(10_100, $response->json('data.payable_amount'));
        $this->assertGreaterThan(10_000, $response->json('data.payable_amount'));
    }

    public function test_checkout_blocks_product_amount_above_otp_threshold_without_verification(): void
    {
        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 10_001,
            'payload' => [],
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Phone verification is required for this purchase amount.',
                'errors' => [
                    'code' => 'OTP_REQUIRED',
                ],
            ]);

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_checkout_blocks_product_amount_above_guest_limit(): void
    {
        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 20_001,
            'payload' => [],
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'errors' => [
                    'code' => 'GUEST_LIMIT_EXCEEDED',
                ],
            ]);

        $this->assertDatabaseCount('transactions', 0);
    }
}
