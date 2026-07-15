<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherRedemption;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use App\Services\FeeService;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Finance\LedgerPostingService;
use App\Services\Finance\PaystackGatewayFeeCalculator;
use Database\Seeders\LaunchVoucherSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesLaunchVouchers;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class Pay033bVoucherPricingAuditTest extends TestCase
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
    }

    /**
     * @return array<string, array{int, int, int, int, int, int}>
     */
    public static function validVoucherPricingScenarios(): array
    {
        return [
            '500 product + 500 voucher' => [500, 500, 0, 100, 103, 203],
            '1000 product + 500 voucher' => [1000, 500, 500, 100, 111, 711],
            '1000 product + 1000 voucher' => [1000, 1000, 0, 100, 103, 203],
            '2000 product + 1000 voucher' => [2000, 1000, 1000, 100, 118, 1218],
        ];
    }

    #[DataProvider('validVoucherPricingScenarios')]
    public function test_voucher_pricing_uses_pre_gateway_charge_for_gateway_fee_recovery(
        int $productAmount,
        int $voucherDiscount,
        int $discountedProduct,
        int $convenienceFee,
        int $gatewayFee,
        int $payableAmount,
    ): void {
        $quote = app(FeeService::class)->quote('airtime', $productAmount, $voucherDiscount);
        $audit = app(PaystackGatewayFeeCalculator::class)->auditVoucherCheckout(
            $productAmount,
            $voucherDiscount,
            FeeService::CONVENIENCE_FEE,
        );

        $this->assertSame($discountedProduct, $quote['net_product_amount']);
        $this->assertSame($discountedProduct + $convenienceFee, $quote['pre_gateway_charge']);
        $this->assertSame($convenienceFee, $quote['convenience_fee']);
        $this->assertSame($gatewayFee, $quote['gateway_fee']);
        $this->assertSame($payableAmount, $quote['payable_amount']);
        $this->assertGreaterThan(0, $quote['payable_amount']);

        $this->assertSame($gatewayFee, $audit['gateway_fee']);
        $this->assertSame($payableAmount, $audit['payable_amount']);
        $this->assertSame($payableAmount * 100, $audit['expected_paystack_charge_kobo']);
        $this->assertGreaterThanOrEqual($audit['gateway_fee_if_product_only'], $gatewayFee);
        $this->assertFalse($audit['negative_margin']);
        $this->assertGreaterThanOrEqual(0, $audit['estimated_gross_margin_kobo']);

        if ($discountedProduct === 0) {
            $this->assertGreaterThan($audit['gateway_fee_if_product_only'], $gatewayFee);
        }
    }

    #[DataProvider('validVoucherPricingScenarios')]
    public function test_voucher_validation_api_matches_fee_service_quote(
        int $productAmount,
        int $voucherDiscount,
        int $discountedProduct,
        int $convenienceFee,
        int $gatewayFee,
        int $payableAmount,
    ): void {
        $fixture = $this->createLaunchVoucherCampaign(amount: $voucherDiscount, quantity: 1);

        $response = $this->postJson('/api/v1/vouchers/validate', [
            'code' => $fixture['code'],
            'product_type' => 'airtime',
            'product_amount' => $productAmount,
            'network' => 'MTN',
            'customer_phone' => '0803999'.str_pad((string) $productAmount, 4, '0', STR_PAD_LEFT),
            'device_id' => 'audit-device-'.$productAmount.'-'.$voucherDiscount,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.discount_amount', $voucherDiscount)
            ->assertJsonPath('data.net_product_amount', $discountedProduct)
            ->assertJsonPath('data.pre_gateway_charge', $discountedProduct + $convenienceFee)
            ->assertJsonPath('data.convenience_fee', $convenienceFee)
            ->assertJsonPath('data.gateway_fee', $gatewayFee)
            ->assertJsonPath('data.payable_amount', $payableAmount);
    }

    #[DataProvider('validVoucherPricingScenarios')]
    public function test_checkout_initialize_persists_voucher_pricing_for_paystack(
        int $productAmount,
        int $voucherDiscount,
        int $discountedProduct,
        int $convenienceFee,
        int $gatewayFee,
        int $payableAmount,
    ): void {
        $fixture = $this->createLaunchVoucherCampaign(amount: $voucherDiscount, quantity: 1);

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '0803888'.str_pad((string) $productAmount, 4, '0', STR_PAD_LEFT),
            'product_amount' => $productAmount,
            'voucher_code' => $fixture['code'],
            'device_id' => 'checkout-device-'.$productAmount.'-'.$voucherDiscount,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '0803888'.str_pad((string) $productAmount, 4, '0', STR_PAD_LEFT),
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.product_amount', $productAmount)
            ->assertJsonPath('data.voucher_discount_amount', $voucherDiscount)
            ->assertJsonPath('data.convenience_fee', $convenienceFee)
            ->assertJsonPath('data.gateway_fee', $gatewayFee)
            ->assertJsonPath('data.payable_amount', $payableAmount);

        $reference = (string) $response->json('data.reference');
        $transaction = Transaction::query()->where('reference', $reference)->firstOrFail();

        $this->assertSame($payableAmount * 100, $transaction->payable_amount * 100);
    }

    public function test_non_voucher_checkout_pricing_is_unchanged(): void
    {
        $quote = app(FeeService::class)->quote('airtime', 1000);

        $this->assertSame(100, $quote['convenience_fee']);
        $this->assertSame(1100, $quote['pre_gateway_charge']);
        $this->assertSame(118, $quote['gateway_fee']);
        $this->assertSame(1218, $quote['payable_amount']);
    }

    public function test_expired_voucher_is_rejected(): void
    {
        $fixture = $this->createLaunchVoucherCampaign(amount: 500, quantity: 1);
        LaunchVoucher::query()->where('id', $fixture['vouchers'][0]->id)->update([
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson('/api/v1/vouchers/validate', [
            'code' => $fixture['code'],
            'product_type' => 'airtime',
            'product_amount' => 1000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_EXPIRED');
    }

    public function test_exhausted_voucher_is_rejected(): void
    {
        $fixture = $this->createLaunchVoucherCampaign(amount: 500, quantity: 1);
        LaunchVoucher::query()->where('id', $fixture['vouchers'][0]->id)->update([
            'redeemed_count' => 1,
            'max_redemptions' => 1,
        ]);

        $this->postJson('/api/v1/vouchers/validate', [
            'code' => $fixture['code'],
            'product_type' => 'airtime',
            'product_amount' => 1000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_EXHAUSTED');
    }

    public function test_invalid_voucher_is_rejected(): void
    {
        $this->postJson('/api/v1/vouchers/validate', [
            'code' => 'NOTAVALIDCODE',
            'product_type' => 'airtime',
            'product_amount' => 1000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_NOT_FOUND');
    }

    public function test_duplicate_device_redemption_is_blocked(): void
    {
        $fixture = $this->createLaunchVoucherCampaign(amount: 500, quantity: 2);
        $voucher = $fixture['vouchers'][0];
        $secondCode = $fixture['vouchers'][1]->code;
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260714-DEV001',
            'product_type' => 'airtime',
            'customer_phone' => '08037770001',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 111,
            'payable_amount' => 711,
            'launch_voucher_id' => $voucher->id,
            'voucher_code' => $voucher->code,
            'voucher_discount_amount' => 500,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
        ]);

        LaunchVoucherRedemption::query()->create([
            'launch_voucher_id' => $voucher->id,
            'campaign_id' => $voucher->campaign_id,
            'transaction_id' => $transaction->id,
            'customer_phone' => '08037770001',
            'customer_phone_normalized' => \App\Support\Marketing\VoucherIdentityNormalizer::normalizePhone('08037770001'),
            'device_id' => 'shared-device-audit',
            'device_id_hash' => \App\Support\Marketing\VoucherIdentityNormalizer::hashDevice('shared-device-audit'),
            'status' => LaunchVoucherRedemption::STATUS_REDEEMED,
            'discount_amount' => 500,
            'reserved_at' => now(),
            'redeemed_at' => now(),
        ]);

        $this->postJson('/api/v1/vouchers/validate', [
            'code' => $secondCode,
            'product_type' => 'airtime',
            'product_amount' => 1000,
            'customer_phone' => '08037779999',
            'device_id' => 'shared-device-audit',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_DEVICE_USED');
    }

    public function test_voucher_ledger_posting_records_marketing_expense_and_balances(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260714-AUD001',
            'product_type' => 'airtime',
            'customer_phone' => '08036660001',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 111,
            'payable_amount' => 711,
            'voucher_code' => 'PYL-TEST-AUD1',
            'voucher_discount_amount' => 500,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
            'request_payload' => ['network' => 'MTN'],
            'response_payload' => ['fulfillment' => ['amount' => 1000]],
        ]);

        $postingService = app(LedgerPostingService::class);
        $postingService->postPaymentReceived($transaction);
        $postingService->postFulfillmentRecognized($transaction->fresh());

        $ledger = app(FinancialLedgerService::class);

        $this->assertSame(50000, $ledger->accountBalance(LedgerAccountCode::MARKETING_PROMOTION_EXPENSE)['balance_kobo']);
        $this->assertSame(100000, $ledger->accountBalance(LedgerAccountCode::VTPASS_PRODUCT_COST)['credits']);
        $this->assertSame(10000, $ledger->accountBalance(LedgerAccountCode::CONVENIENCE_FEE_REVENUE)['credits']);
        $this->assertSame(11100, $ledger->accountBalance(LedgerAccountCode::GATEWAY_FEE_RECOVERY)['credits']);

        $debits = (int) LedgerEntry::query()->where('entry_type', 'debit')->sum('amount_kobo');
        $credits = (int) LedgerEntry::query()->where('entry_type', 'credit')->sum('amount_kobo');
        $this->assertSame($debits, $credits);

        $financial = $transaction->fresh()->financial;
        $this->assertNotNull($financial);
        $this->assertGreaterThanOrEqual(0, $financial->gross_margin_kobo);
    }

    public function test_repeated_fulfillment_posting_does_not_duplicate_voucher_expense(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260714-AUD002',
            'product_type' => 'airtime',
            'customer_phone' => '08036660002',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 103,
            'payable_amount' => 203,
            'voucher_code' => 'PYL-TEST-AUD2',
            'voucher_discount_amount' => 1000,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
            'request_payload' => ['network' => 'MTN'],
            'response_payload' => ['fulfillment' => ['amount' => 1000]],
        ]);

        $postingService = app(LedgerPostingService::class);
        $postingService->postPaymentReceived($transaction);
        $postingService->postFulfillmentRecognized($transaction->fresh());
        $postingService->postFulfillmentRecognized($transaction->fresh());

        $this->assertSame(
            1,
            LedgerTransaction::query()->where('event_type', LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED)->count(),
        );

        $marketingExpense = app(FinancialLedgerService::class)
            ->accountBalance(LedgerAccountCode::MARKETING_PROMOTION_EXPENSE)['balance_kobo'];

        $this->assertSame(100000, $marketingExpense);
    }
}
