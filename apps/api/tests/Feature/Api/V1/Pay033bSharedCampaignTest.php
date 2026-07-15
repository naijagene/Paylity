<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherRedemption;
use App\Models\Transaction;
use App\Services\Marketing\LaunchVoucherReservationCleanupService;
use App\Services\Marketing\LaunchVoucherService;
use App\Services\Payments\PaystackService;
use App\Support\Marketing\VoucherIdentityNormalizer;
use Database\Seeders\LaunchVoucherSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLaunchVouchers;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class Pay033bSharedCampaignTest extends TestCase
{
    use CreatesLaunchVouchers;
    use RefreshDatabase;
    use SeedsProductCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.paystack.enabled' => true]);
        $this->seed(PlatformSettingsSeeder::class);
        $this->seed(LedgerAccountSeeder::class);
        $this->seed(LaunchVoucherSeeder::class);
        $this->seedProductCatalog();

        $this->mock(PaystackService::class, function ($mock): void {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('hasSecretKey')->andReturn(true);
            $mock->shouldReceive('initializeTransaction')->andReturn([
                'reference' => 'PSK-SHARED',
                'authorization_url' => 'https://checkout.paystack.com/shared',
                'raw' => ['data' => ['access_code' => 'ac_shared']],
            ]);
        });
    }

    public function test_shared_code_supports_five_distinct_customers(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 5);

        for ($index = 1; $index <= 5; $index++) {
            $phone = '0803100000'.$index;
            $this->postJson('/api/v1/checkout/initialize', [
                'product_type' => 'airtime',
                'customer_phone' => $phone,
                'customer_email' => "user{$index}@example.com",
                'product_amount' => 500,
                'voucher_code' => $fixture['code'],
                'device_id' => "device-shared-{$index}",
                'payload' => ['network' => 'MTN', 'recipient_phone' => $phone],
            ])->assertCreated();
        }

        $this->assertSame(5, LaunchVoucherRedemption::query()->where('campaign_id', $fixture['campaign']->id)->where('status', 'reserved')->count());
    }

    public function test_same_phone_cannot_redeem_shared_code_twice(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 5);

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031112222',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'device-a',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08031112222'],
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031112222',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'device-b',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08031112222'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_PHONE_USED');
    }

    public function test_same_device_with_different_phone_is_blocked(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 5);

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08032223333',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'shared-device-1',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08032223333'],
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08034445555',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'shared-device-1',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08034445555'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_DEVICE_USED');
    }

    public function test_same_email_with_different_phone_is_blocked(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 5);

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08035556666',
            'customer_email' => 'shared@example.com',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'device-email-1',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08035556666'],
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08036667777',
            'customer_email' => 'shared@example.com',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'device-email-2',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08036667777'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_EMAIL_USED');
    }

    public function test_campaign_stops_at_maximum_reservation_capacity(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 2);

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08037770001',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'cap-device-1',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08037770001'],
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08037770002',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'cap-device-2',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08037770002'],
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08037770003',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'cap-device-3',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08037770003'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_CAMPAIGN_EXHAUSTED');
    }

    public function test_released_reservation_restores_shared_campaign_capacity(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 1);
        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08038880001',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'release-device-1',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08038880001'],
        ])->assertCreated();

        $transaction = Transaction::query()->where('reference', $response->json('data.reference'))->firstOrFail();
        app(LaunchVoucherService::class)->releaseReservation($transaction, 'payment_failed');

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08038880002',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'release-device-2',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08038880002'],
        ])->assertCreated();
    }

    public function test_phone_formats_are_treated_as_identical(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 5);

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'fmt-device-1',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08031234567'],
        ])->assertCreated();

        $this->postJson('/api/v1/vouchers/validate', [
            'code' => $fixture['code'],
            'product_type' => 'airtime',
            'product_amount' => 500,
            'customer_phone' => '+2348031234567',
            'device_id' => 'fmt-device-2',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_PHONE_USED');

        $this->assertSame(
            VoucherIdentityNormalizer::normalizePhone('08031234567'),
            VoucherIdentityNormalizer::normalizePhone('+2348031234567'),
        );
    }

    public function test_unique_code_campaign_still_blocks_same_phone_on_second_code(): void
    {
        $fixture = $this->createLaunchVoucherCampaign(amount: 500, quantity: 2);
        $firstCode = $fixture['vouchers'][0]->code;
        $secondCode = $fixture['vouchers'][1]->code;

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08030001234',
            'product_amount' => 500,
            'voucher_code' => $firstCode,
            'device_id' => 'unique-device-1',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08030001234'],
        ])->assertCreated();

        $this->postJson('/api/v1/vouchers/validate', [
            'code' => $secondCode,
            'product_type' => 'airtime',
            'product_amount' => 500,
            'customer_phone' => '08030001234',
            'device_id' => 'unique-device-2',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_PHONE_USED');
    }

    public function test_ops_can_create_shared_campaign_with_distribution_mode(): void
    {
        config(['services.operator.access_key' => 'test-operator-key']);

        $response = $this->withHeaders(['X-Operator-Key' => 'test-operator-key'])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Soft Launch Test',
                'amount' => 500,
                'distribution_mode' => 'shared_code',
                'max_redemptions' => 5,
                'one_per_phone' => true,
                'one_per_device' => true,
                'one_per_email' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.campaign.distribution_mode', 'shared_code')
            ->assertJsonPath('data.campaign.max_redemptions', 5)
            ->assertJsonCount(1, 'data.codes');
    }

    public function test_duplicate_webhook_does_not_increment_redemption_twice(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 5);
        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08030009999',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'dup-webhook-device',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08030009999'],
        ])->assertCreated();

        $transaction = Transaction::query()->where('reference', $response->json('data.reference'))->firstOrFail();
        $transaction->update(['status' => TransactionStatus::FULFILLED]);

        $service = app(LaunchVoucherService::class);
        $service->completeRedemption($transaction);
        $service->completeRedemption($transaction->fresh());

        $voucher = LaunchVoucher::query()->findOrFail($fixture['vouchers'][0]->id);
        $this->assertSame(1, $voucher->fresh()->redeemed_count);
        $this->assertSame(1, LaunchVoucherRedemption::query()->where('status', 'redeemed')->count());
    }

    public function test_soft_launch_acceptance_scenario(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(
            amount: 500,
            maxRedemptions: 5,
            overrides: ['name' => 'Soft Launch Test'],
        );

        for ($index = 1; $index <= 5; $index++) {
            $this->postJson('/api/v1/checkout/initialize', [
                'product_type' => 'airtime',
                'customer_phone' => '0803200000'.$index,
                'customer_email' => "launch{$index}@example.com",
                'product_amount' => 500,
                'voucher_code' => $fixture['code'],
                'device_id' => "launch-device-{$index}",
                'payload' => ['network' => 'MTN', 'recipient_phone' => '0803200000'.$index],
            ])->assertCreated();
        }

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08032000006',
            'customer_email' => 'launch6@example.com',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'launch-device-6',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08032000006'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_CAMPAIGN_EXHAUSTED');

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08032000001',
            'customer_email' => 'launch1@example.com',
            'product_amount' => 500,
            'voucher_code' => $fixture['code'],
            'device_id' => 'launch-device-retry',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08032000001'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_PHONE_USED');

        $this->assertSame(5, LaunchVoucherRedemption::query()->where('campaign_id', $fixture['campaign']->id)->where('status', 'reserved')->count());
        $this->assertSame(0, app(\App\Services\Marketing\LaunchVoucherCampaignCapacityService::class)->remainingCapacity($fixture['campaign']->fresh()));
    }
}
