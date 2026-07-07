<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OtpPurpose;
use App\Enums\OtpStatus;
use App\Models\OtpVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\SeedsPlatformSettings;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class OtpApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPlatformSettings;
    use SeedsProductCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlatformSettings();
    }

    public function test_request_otp_returns_reference_and_masked_phone(): void
    {
        $response = $this->postJson('/api/v1/otp/request', [
            'phone' => '08031234567',
            'purpose' => OtpPurpose::CHECKOUT->value,
            'amount' => 15_000,
            'product_type' => 'airtime',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'otp_reference',
                    'expires_at',
                    'resend_available_at',
                    'masked_phone',
                ],
            ]);

        $this->assertDatabaseHas('otp_verifications', [
            'phone' => '08031234567',
            'purpose' => OtpPurpose::CHECKOUT->value,
            'status' => OtpStatus::PENDING->value,
        ]);
    }

    public function test_verify_valid_otp_returns_verification_token(): void
    {
        $record = OtpVerification::query()->create([
            'purpose' => OtpPurpose::CHECKOUT,
            'phone' => '08031234567',
            'code_hash' => Hash::make('123456'),
            'reference' => 'OTP-TEST123456',
            'status' => OtpStatus::PENDING,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => Carbon::now()->addMinutes(10),
            'metadata' => ['amount' => 15_000],
        ]);

        $response = $this->postJson('/api/v1/otp/verify', [
            'otp_reference' => $record->reference,
            'code' => '123456',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonStructure(['data' => ['verification_token']]);

        $record->refresh();
        $this->assertSame(OtpStatus::VERIFIED, $record->status);
        $this->assertNotNull($record->verified_at);
    }

    public function test_verify_rejects_wrong_otp(): void
    {
        $record = OtpVerification::query()->create([
            'purpose' => OtpPurpose::CHECKOUT,
            'phone' => '08031234567',
            'code_hash' => Hash::make('123456'),
            'reference' => 'OTP-WRONGCODE1',
            'status' => OtpStatus::PENDING,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/otp/verify', [
            'otp_reference' => $record->reference,
            'code' => '654321',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'OTP_INVALID');

        $this->assertSame(1, $record->fresh()->attempts);
    }

    public function test_verify_rejects_expired_otp(): void
    {
        $record = OtpVerification::query()->create([
            'purpose' => OtpPurpose::CHECKOUT,
            'phone' => '08031234567',
            'code_hash' => Hash::make('123456'),
            'reference' => 'OTP-EXPIRED001',
            'status' => OtpStatus::PENDING,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/otp/verify', [
            'otp_reference' => $record->reference,
            'code' => '123456',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'OTP_EXPIRED');

        $this->assertSame(OtpStatus::EXPIRED, $record->fresh()->status);
    }

    public function test_verify_locks_out_after_max_attempts(): void
    {
        $record = OtpVerification::query()->create([
            'purpose' => OtpPurpose::CHECKOUT,
            'phone' => '08031234567',
            'code_hash' => Hash::make('123456'),
            'reference' => 'OTP-MAXATTEMPT',
            'status' => OtpStatus::PENDING,
            'attempts' => 4,
            'max_attempts' => 5,
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/otp/verify', [
            'otp_reference' => $record->reference,
            'code' => '654321',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'OTP_MAX_ATTEMPTS');

        $this->assertSame(OtpStatus::FAILED, $record->fresh()->status);
    }

    public function test_resend_enforces_cooldown(): void
    {
        $record = OtpVerification::query()->create([
            'purpose' => OtpPurpose::CHECKOUT,
            'phone' => '08031234567',
            'code_hash' => Hash::make('123456'),
            'reference' => 'OTP-COOLDOWN01',
            'status' => OtpStatus::PENDING,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => Carbon::now()->addMinutes(10),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $response = $this->postJson('/api/v1/otp/resend', [
            'otp_reference' => $record->reference,
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('errors.code', 'OTP_RESEND_COOLDOWN');
    }
}
