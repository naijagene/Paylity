<?php

namespace Tests\Feature\Console;

use App\Services\FeeService;
use App\Services\Finance\PaystackGatewayFeeCalculator;
use App\Services\Launch\LaunchModeService;
use App\Services\Launch\PricingAuditService;
use App\Services\Launch\SchedulerHeartbeatService;
use App\Support\Platform\FeatureFlagKeys;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use App\Models\FeatureFlag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Pay033LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_pricing_audit_has_no_negative_margin_for_launch_amounts(): void
    {
        config(['services.paystack.enabled' => true]);
        FeatureFlag::query()->where('key', FeatureFlagKeys::PAYSTACK)->update(['enabled' => true]);

        $audit = app(PricingAuditService::class)->audit('airtime');

        $this->assertTrue($audit['all_positive']);
        $this->assertSame(0, $audit['negative_margin_count']);

        $oneThousand = collect($audit['amounts'])->firstWhere('product_amount', 1000);
        $this->assertNotNull($oneThousand);
        $this->assertSame(118, $oneThousand['gateway_fee']);
        $this->assertSame(1218, $oneThousand['payable_amount']);
        $this->assertGreaterThanOrEqual(0, $oneThousand['estimated_gross_margin_kobo']);
    }

    public function test_fee_service_quotes_gateway_fee_when_paystack_enabled(): void
    {
        config(['services.paystack.enabled' => true]);
        FeatureFlag::query()->where('key', FeatureFlagKeys::PAYSTACK)->update(['enabled' => true]);
        $this->seed(LedgerAccountSeeder::class);

        $quote = app(FeeService::class)->quote('airtime', 1000);

        $this->assertSame(100, $quote['convenience_fee']);
        $this->assertGreaterThan(0, $quote['gateway_fee']);
        $this->assertSame(1000 + 100 + $quote['gateway_fee'], $quote['payable_amount']);
    }

    public function test_scheduler_heartbeat_records_and_reports_healthy(): void
    {
        $service = app(SchedulerHeartbeatService::class);
        $service->record();

        $snapshot = $service->snapshot();

        $this->assertSame(SchedulerHeartbeatService::STATUS_HEALTHY, $snapshot['status']);
        $this->assertNotNull($snapshot['last_run_at']);
    }

    public function test_launch_preflight_command_runs(): void
    {
        $this->seed(LedgerAccountSeeder::class);

        $this->artisan('paylity:launch-preflight', ['--json' => true])
            ->assertExitCode(1);
    }

    public function test_pricing_audit_command_fails_when_negative_margin_detected(): void
    {
        $calculator = app(PaystackGatewayFeeCalculator::class);
        $this->assertFalse($calculator->auditLaunchAmount(1000, 100, 1000)['negative_margin'] === true);
    }

    public function test_soft_launch_blocks_when_daily_transaction_cap_reached(): void
    {
        app(\App\Services\Platform\SystemSettingsService::class)->setMany([
            \App\Support\Platform\SystemSettingKeys::LAUNCH_MODE => LaunchModeService::MODE_SOFT_LAUNCH,
            \App\Support\Platform\SystemSettingKeys::LAUNCH_TRANSACTION_LIMIT_DAILY => 1,
        ]);

        \App\Models\Transaction::query()->create([
            'reference' => 'PYL-20260714-CAP01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 117,
            'payable_amount' => 1217,
            'currency' => 'NGN',
            'status' => \App\Enums\TransactionStatus::FULFILLED,
            'verified_phone' => false,
            'created_at' => now(),
        ]);

        $this->expectException(\App\Exceptions\FraudCheckException::class);
        app(LaunchModeService::class)->assertCheckoutAllowed('airtime', 1217);
    }
}
