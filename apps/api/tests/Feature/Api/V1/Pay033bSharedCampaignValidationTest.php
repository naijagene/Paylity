<?php

namespace Tests\Feature\Api\V1;

use App\Models\LaunchVoucher;
use App\Services\Payments\PaystackService;
use App\Support\Marketing\LaunchVoucherCodeGenerator;
use Database\Seeders\LaunchVoucherSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class Pay033bSharedCampaignValidationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.operator.access_key' => self::OPERATOR_KEY,
            'services.paystack.enabled' => true,
        ]);

        $this->seed(PlatformSettingsSeeder::class);
        $this->seed(LedgerAccountSeeder::class);
        $this->seed(LaunchVoucherSeeder::class);
        $this->seedProductCatalog();

        $this->mock(PaystackService::class, function ($mock): void {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('hasSecretKey')->andReturn(true);
            $mock->shouldReceive('initializeTransaction')->andReturn([
                'reference' => 'PSK-VALIDATION',
                'authorization_url' => 'https://checkout.paystack.com/validation',
                'raw' => ['data' => ['access_code' => 'ac_validation']],
            ]);
        });
    }

    public function test_unique_codes_with_quantity_creates_five_codes(): void
    {
        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Unique Launch',
                'amount' => 500,
                'distribution_mode' => 'unique_codes',
                'quantity' => 5,
            ]);

        $response
            ->assertCreated()
            ->assertJsonCount(5, 'data.codes')
            ->assertJsonPath('data.campaign.distribution_mode', 'unique_codes')
            ->assertJsonPath('data.campaign.generated_count', 5);

        $codes = collect($response->json('data.codes'));
        $this->assertSame(5, $codes->unique()->count());
        $this->assertTrue($codes->every(fn (string $code) => app(LaunchVoucherCodeGenerator::class)->isValidFormat($code)));
        $campaignId = $response->json('data.campaign.id');
        $this->assertSame(5, LaunchVoucher::query()->where('campaign_id', $campaignId)->count());
        $this->assertSame(
            5,
            LaunchVoucher::query()->where('campaign_id', $campaignId)->where('max_redemptions', 1)->count(),
        );
    }

    public function test_unique_codes_without_quantity_returns_validation_error(): void
    {
        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Unique Launch',
                'amount' => 500,
                'distribution_mode' => 'unique_codes',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_shared_code_with_max_redemptions_creates_one_code(): void
    {
        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Airtime Launch Promo',
                'amount' => 1000,
                'distribution_mode' => 'shared_code',
                'max_redemptions' => 2,
                'one_per_phone' => true,
                'one_per_email' => true,
                'one_per_device' => true,
                'active' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonCount(1, 'data.codes')
            ->assertJsonPath('data.campaign.distribution_mode', 'shared_code')
            ->assertJsonPath('data.campaign.max_redemptions', 2)
            ->assertJsonPath('data.campaign.generated_count', 1);
    }

    public function test_shared_code_without_max_redemptions_returns_validation_error(): void
    {
        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Shared Launch',
                'amount' => 500,
                'distribution_mode' => 'shared_code',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_redemptions']);
    }

    public function test_shared_code_does_not_require_quantity(): void
    {
        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Shared Launch',
                'amount' => 500,
                'distribution_mode' => 'shared_code',
                'max_redemptions' => 2,
            ])
            ->assertCreated()
            ->assertJsonMissingValidationErrors(['quantity']);
    }

    public function test_shared_code_rejects_quantity_field(): void
    {
        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Shared Launch',
                'amount' => 500,
                'distribution_mode' => 'shared_code',
                'max_redemptions' => 2,
                'quantity' => 5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_generated_shared_voucher_has_matching_max_redemptions(): void
    {
        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Shared Launch',
                'amount' => 500,
                'distribution_mode' => 'shared_code',
                'max_redemptions' => 2,
            ])
            ->assertCreated();

        $code = $response->json('data.codes.0');

        $this->assertDatabaseHas('launch_vouchers', [
            'code' => $code,
            'max_redemptions' => 2,
        ]);
    }

    public function test_shared_campaign_blocks_third_customer_after_two_reservations(): void
    {
        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Airtime Launch Promo',
                'amount' => 1000,
                'distribution_mode' => 'shared_code',
                'max_redemptions' => 2,
                'one_per_phone' => true,
                'one_per_device' => true,
                'one_per_email' => true,
                'active' => true,
            ])
            ->assertCreated();

        $code = $response->json('data.codes.0');

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08034000001',
            'customer_email' => 'launch1@example.com',
            'product_amount' => 1000,
            'voucher_code' => $code,
            'device_id' => 'launch-device-1',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08034000001'],
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08034000002',
            'customer_email' => 'launch2@example.com',
            'product_amount' => 1000,
            'voucher_code' => $code,
            'device_id' => 'launch-device-2',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08034000002'],
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08034000003',
            'customer_email' => 'launch3@example.com',
            'product_amount' => 1000,
            'voucher_code' => $code,
            'device_id' => 'launch-device-3',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08034000003'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_CAMPAIGN_EXHAUSTED');

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08034000001',
            'customer_email' => 'launch1@example.com',
            'product_amount' => 1000,
            'voucher_code' => $code,
            'device_id' => 'launch-device-retry',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08034000001'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_PHONE_USED');
    }
}
