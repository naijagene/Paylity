<?php

namespace Tests\Feature\Api\V1;

use App\Enums\LedgerAccountCode;
use App\Enums\TransactionStatus;
use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherRedemption;
use App\Models\Transaction;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Finance\LedgerPostingService;
use App\Services\Marketing\LaunchVoucherService;
use App\Services\Marketing\MarketingEventService;
use App\Services\Marketing\TransactionReviewService;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\LaunchVoucherSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Pay033bLaunchVoucherTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.operator.access_key' => self::OPERATOR_KEY,
            'services.paystack.enabled' => false,
        ]);

        $this->seed(PlatformSettingsSeeder::class);
        $this->seed(LedgerAccountSeeder::class);
        $this->seed(LaunchVoucherSeeder::class);
    }

    public function test_voucher_validation_returns_updated_payable_amount(): void
    {
        $response = $this->postJson('/api/v1/vouchers/validate', [
            'code' => 'PAYLITY500',
            'product_type' => 'airtime',
            'product_amount' => 1000,
            'network' => 'MTN',
            'customer_phone' => '08031234567',
            'device_id' => 'device-test-1',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.discount_amount', 500)
            ->assertJsonPath('data.payable_amount', fn ($value) => $value < 1000);
    }

    public function test_duplicate_phone_is_blocked_for_one_per_phone_voucher(): void
    {
        $voucher = LaunchVoucher::query()->where('code', 'PAYLITY500')->firstOrFail();
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260714-VCH001',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 600,
            'launch_voucher_id' => $voucher->id,
            'voucher_code' => 'PAYLITY500',
            'voucher_discount_amount' => 500,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
        ]);

        LaunchVoucherRedemption::query()->create([
            'launch_voucher_id' => $voucher->id,
            'transaction_id' => $transaction->id,
            'customer_phone' => '08031234567',
            'status' => LaunchVoucherRedemption::STATUS_COMPLETED,
            'discount_amount' => 500,
            'redeemed_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/vouchers/validate', [
            'code' => 'PAYLITY500',
            'product_type' => 'airtime',
            'product_amount' => 1000,
            'customer_phone' => '08031234567',
            'device_id' => 'device-test-2',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_PHONE_USED');
    }

    public function test_expired_voucher_is_rejected(): void
    {
        LaunchVoucher::query()->where('code', 'PAYLITY500')->update([
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/v1/vouchers/validate', [
            'code' => 'PAYLITY500',
            'product_type' => 'airtime',
            'product_amount' => 1000,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VOUCHER_EXPIRED');
    }

    public function test_ledger_posting_records_marketing_promotion_expense_for_voucher(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260714-VCH002',
            'product_type' => 'airtime',
            'customer_phone' => '08030001111',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 600,
            'voucher_code' => 'PAYLITY500',
            'voucher_discount_amount' => 500,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
            'request_payload' => ['network' => 'MTN'],
            'response_payload' => ['fulfillment' => ['amount' => 1000]],
        ]);

        app(LedgerPostingService::class)->postPaymentReceived($transaction);
        app(LedgerPostingService::class)->postFulfillmentRecognized($transaction);

        $marketingExpense = app(FinancialLedgerService::class)
            ->accountBalance(LedgerAccountCode::MARKETING_PROMOTION_EXPENSE)['balance_kobo'];

        $this->assertSame(50000, $marketingExpense);
    }

    public function test_review_submission_and_share_tracking(): void
    {
        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260714-REV001',
            'product_type' => 'airtime',
            'customer_phone' => '08030002222',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::FULFILLED,
            'verified_phone' => false,
        ]);

        $this->postJson('/api/v1/transactions/PYL-20260714-REV001/review', [
            'rating' => 5,
            'comment' => 'Fast delivery',
        ])->assertCreated();

        $this->postJson('/api/v1/transactions/PYL-20260714-REV001/share', [
            'channel' => 'whatsapp',
        ])->assertOk();

        $this->assertSame(1, app(TransactionReviewService::class)->aggregateStats()['count']);
        $this->assertDatabaseHas('marketing_events', [
            'event_type' => MarketingEventService::TYPE_SHARE_INITIATED,
            'reference' => 'PYL-20260714-REV001',
        ]);
    }

    public function test_ops_marketing_snapshot_lists_seeded_vouchers(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/marketing/vouchers');

        $response
            ->assertOk()
            ->assertJsonPath('data.kpis.generated', 2)
            ->assertJsonFragment(['code' => 'PAYLITY500'])
            ->assertJsonFragment(['code' => 'PAYLITY1000']);
    }
}
