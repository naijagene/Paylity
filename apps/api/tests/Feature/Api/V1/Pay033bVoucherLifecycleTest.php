<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherRedemption;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Finance\LedgerPostingService;
use App\Services\Marketing\LaunchVoucherService;
use App\Services\Payments\PaystackService;
use App\Support\Marketing\LaunchVoucherCodeGenerator;
use Database\Seeders\LaunchVoucherSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLaunchVouchers;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class Pay033bVoucherLifecycleTest extends TestCase
{
    use CreatesLaunchVouchers;
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
    }

    public function test_secure_code_generator_produces_valid_unique_codes(): void
    {
        $generator = app(LaunchVoucherCodeGenerator::class);
        $first = $generator->generateUnique();
        $second = $generator->generateUnique();

        $this->assertNotSame($first, $second);
        $this->assertTrue($generator->isValidFormat($first));
        $this->assertMatchesRegularExpression('/^PYL-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{4}-[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{4}$/', $first);
        $this->assertSame($first, $generator->formatForDisplay($generator->normalize($first)));
    }

    public function test_legacy_predictable_codes_are_inactive_after_seeding(): void
    {
        foreach (['PAYLITY500', 'PAYLITY1000'] as $code) {
            $voucher = LaunchVoucher::query()->where('code', $code)->first();
            $this->assertNotNull($voucher);
            $this->assertFalse($voucher->active);
        }

        $this->seed(LaunchVoucherSeeder::class);

        foreach (['PAYLITY500', 'PAYLITY1000'] as $code) {
            $this->assertFalse(LaunchVoucher::query()->where('code', $code)->value('active'));
        }
    }

    public function test_ops_campaign_generates_unique_one_time_codes(): void
    {
        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/marketing/campaigns', [
                'name' => 'Staging Launch',
                'amount' => 500,
                'quantity' => 5,
                'one_per_phone' => true,
                'one_per_device' => true,
            ]);

        $response
            ->assertCreated()
            ->assertJsonCount(5, 'data.codes')
            ->assertJsonPath('data.campaign.generated_count', 5);

        $codes = collect($response->json('data.codes'));
        $this->assertSame(5, $codes->unique()->count());
        $this->assertTrue($codes->every(fn (string $code) => app(LaunchVoucherCodeGenerator::class)->isValidFormat($code)));
    }

    public function test_checkout_initialize_applies_voucher_authoritatively_for_500_airtime(): void
    {
        $fixture = $this->createLaunchVoucherCampaign(amount: 500, quantity: 1);
        $code = $fixture['code'];

        $this->mock(PaystackService::class, function ($mock): void {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('hasSecretKey')->andReturn(true);
            $mock->shouldReceive('initializeTransaction')
                ->once()
                ->with(\Mockery::on(function (Transaction $transaction) {
                    return $transaction->payable_amount === 203
                        && $transaction->voucher_discount_amount === 500
                        && $transaction->gateway_fee === 103;
                }))
                ->andReturn([
                    'reference' => 'PSK-TEST-203',
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'raw' => ['data' => ['access_code' => 'ac_test']],
                ]);
        });

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031112222',
            'product_amount' => 500,
            'voucher_code' => $code,
            'device_id' => 'device-lifecycle-1',
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031112222',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.payable_amount', 203)
            ->assertJsonPath('data.voucher_discount_amount', 500)
            ->assertJsonPath('data.net_product_amount', 0)
            ->assertJsonPath('data.gateway_fee', 103)
            ->assertJsonPath('data.voucher_code_masked', '••••'.substr(app(LaunchVoucherCodeGenerator::class)->normalize($code), -4));

        $transaction = Transaction::query()->where('reference', $response->json('data.reference'))->firstOrFail();
        $this->assertSame(500, $transaction->voucher_discount_amount);
        $this->assertDatabaseHas('launch_voucher_redemptions', [
            'transaction_id' => $transaction->id,
            'status' => LaunchVoucherRedemption::STATUS_RESERVED,
            'discount_amount' => 500,
        ]);
    }

    public function test_same_code_cannot_be_reused_while_reserved(): void
    {
        $fixture = $this->createLaunchVoucherCampaign(amount: 500, quantity: 1);
        $code = $fixture['code'];

        $this->mock(PaystackService::class, function ($mock): void {
            $mock->shouldReceive('isEnabled')->andReturn(true);
            $mock->shouldReceive('hasSecretKey')->andReturn(true);
            $mock->shouldReceive('initializeTransaction')->andReturn([
                'reference' => 'PSK-TEST',
                'authorization_url' => 'https://checkout.paystack.com/test',
                'raw' => ['data' => ['access_code' => 'ac_test']],
            ]);
        });

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08032223333',
            'product_amount' => 500,
            'voucher_code' => $code,
            'device_id' => 'device-a',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08032223333'],
        ])->assertCreated();

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08034445555',
            'product_amount' => 500,
            'voucher_code' => $code,
            'device_id' => 'device-b',
            'payload' => ['network' => 'MTN', 'recipient_phone' => '08034445555'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_CODE_USED');
    }

    public function test_same_phone_is_blocked_for_second_campaign_code(): void
    {
        $fixture = $this->createLaunchVoucherCampaign(amount: 500, quantity: 2);
        $firstCode = $fixture['vouchers'][0]->code;
        $secondCode = $fixture['vouchers'][1]->code;

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260714-LIFE01',
            'product_type' => 'airtime',
            'customer_phone' => '08035556666',
            'product_amount' => 500,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 203,
            'launch_voucher_id' => $fixture['vouchers'][0]->id,
            'voucher_code' => $firstCode,
            'voucher_discount_amount' => 500,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
        ]);

        LaunchVoucherRedemption::query()->create([
            'launch_voucher_id' => $fixture['vouchers'][0]->id,
            'transaction_id' => $transaction->id,
            'customer_phone' => '08035556666',
            'device_id' => 'device-phone-1',
            'status' => LaunchVoucherRedemption::STATUS_REDEEMED,
            'discount_amount' => 500,
            'reserved_at' => now(),
            'redeemed_at' => now(),
        ]);

        $this->postJson('/api/v1/vouchers/validate', [
            'code' => $secondCode,
            'product_type' => 'airtime',
            'product_amount' => 500,
            'customer_phone' => '08035556666',
            'device_id' => 'device-phone-2',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_PHONE_USED');
    }

    public function test_payment_failure_releases_reservation(): void
    {
        $fixture = $this->createLaunchVoucherCampaign(amount: 500, quantity: 1);
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260714-LIFE02',
            'product_type' => 'airtime',
            'customer_phone' => '08036667777',
            'product_amount' => 500,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 203,
            'launch_voucher_id' => $fixture['vouchers'][0]->id,
            'voucher_code' => $fixture['code'],
            'voucher_discount_amount' => 500,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_FAILED,
            'verified_phone' => false,
        ]);

        LaunchVoucherRedemption::query()->create([
            'launch_voucher_id' => $fixture['vouchers'][0]->id,
            'transaction_id' => $transaction->id,
            'customer_phone' => '08036667777',
            'status' => LaunchVoucherRedemption::STATUS_RESERVED,
            'discount_amount' => 500,
            'reserved_at' => now(),
        ]);

        app(LaunchVoucherService::class)->releaseReservation($transaction, 'payment_failed');

        $this->assertDatabaseHas('launch_voucher_redemptions', [
            'transaction_id' => $transaction->id,
            'status' => LaunchVoucherRedemption::STATUS_RELEASED,
        ]);
    }

    public function test_duplicate_fulfillment_does_not_duplicate_marketing_expense(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260714-LIFE03',
            'product_type' => 'airtime',
            'customer_phone' => '08037778888',
            'product_amount' => 500,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 203,
            'voucher_code' => 'PYL-TEST-CODE1',
            'voucher_discount_amount' => 500,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
            'request_payload' => ['network' => 'MTN'],
            'response_payload' => ['fulfillment' => ['amount' => 500]],
        ]);

        $postingService = app(LedgerPostingService::class);
        $postingService->postPaymentReceived($transaction);
        $postingService->postFulfillmentRecognized($transaction->fresh());
        $postingService->postFulfillmentRecognized($transaction->fresh());

        $this->assertSame(
            1,
            LedgerTransaction::query()->where('event_type', LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED)->count(),
        );

        $this->assertSame(
            50000,
            app(FinancialLedgerService::class)->accountBalance(LedgerAccountCode::MARKETING_PROMOTION_EXPENSE)['balance_kobo'],
        );
    }
}
