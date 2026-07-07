<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OtpPurpose;
use App\Enums\OtpStatus;
use App\Models\OtpVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsPlatformSettings;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class CheckoutOtpIntegrationTest extends TestCase
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

    public function test_checkout_returns_structured_otp_required_response(): void
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
                'message' => 'OTP verification is required for this purchase.',
                'errors' => [
                    'code' => 'OTP_REQUIRED',
                    'otp_required' => true,
                ],
            ])
            ->assertJsonStructure([
                'errors' => [
                    'policy' => [
                        'guest_limit',
                        'otp_threshold',
                        'registration_threshold',
                    ],
                ],
            ]);
    }

    public function test_checkout_blocks_otp_required_purchase_without_token(): void
    {
        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 12_000,
            'payload' => [],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_checkout_allows_otp_required_purchase_with_valid_token(): void
    {
        $verificationToken = Str::uuid()->toString();

        OtpVerification::query()->create([
            'purpose' => OtpPurpose::CHECKOUT,
            'phone' => '08031234567',
            'code_hash' => Hash::make('123456'),
            'reference' => 'OTP-CHECKOUT01',
            'status' => OtpStatus::VERIFIED,
            'attempts' => 1,
            'max_attempts' => 5,
            'expires_at' => Carbon::now()->addMinutes(10),
            'verified_at' => Carbon::now(),
            'metadata' => [
                'amount' => 12_000,
                'verification_token_hash' => Hash::make($verificationToken),
                'verification_expires_at' => Carbon::now()->addMinutes(30)->toIso8601String(),
            ],
        ]);

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 12_000,
            'verification_token' => $verificationToken,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('transactions', [
            'product_amount' => 12_000,
            'verified_phone' => true,
        ]);
    }
}
