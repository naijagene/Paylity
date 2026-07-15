<?php

namespace Tests\Feature\Api\V1;

use App\Models\LaunchVoucher;
use App\Models\LaunchVoucherCampaign;
use App\Models\LaunchVoucherRedemption;
use App\Models\MarketingEvent;
use App\Services\Marketing\MarketingEventService;
use Database\Seeders\LaunchVoucherSeeder;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesLaunchVouchers;
use Tests\TestCase;

class Pay034VoucherDashboardTest extends TestCase
{
    use CreatesLaunchVouchers;
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.operator.access_key' => self::OPERATOR_KEY]);
        $this->seed(PlatformSettingsSeeder::class);
        $this->seed(LedgerAccountSeeder::class);
        $this->seed(LaunchVoucherSeeder::class);
    }

    public function test_dashboard_metrics_endpoint_returns_required_kpis(): void
    {
        $campaignCountBefore = LaunchVoucherCampaign::query()->count();
        $voucherCountBefore = LaunchVoucher::query()->count();

        $this->createSharedLaunchVoucherCampaign(maxRedemptions: 2);
        $this->createLaunchVoucherCampaign(amount: 500, quantity: 3);

        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/marketing/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.kpis.total_campaigns', $campaignCountBefore + 2)
            ->assertJsonPath('data.kpis.shared_campaigns', fn ($value) => $value >= 1)
            ->assertJsonPath('data.kpis.unique_campaigns', fn ($value) => $value >= 1)
            ->assertJsonPath('data.kpis.generated_codes', $voucherCountBefore + 4)
            ->assertJsonStructure([
                'data' => [
                    'kpis' => [
                        'total_campaigns',
                        'active_campaigns',
                        'expired_campaigns',
                        'shared_campaigns',
                        'unique_campaigns',
                        'generated_codes',
                        'successful_redemptions',
                        'remaining_capacity',
                        'blocked_attempts',
                        'expired_reservations',
                    ],
                ],
            ]);
    }

    public function test_campaign_detail_endpoint_returns_capacity_and_statistics(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 5);

        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/marketing/campaigns/'.$fixture['campaign']->id);

        $response
            ->assertOk()
            ->assertJsonPath('data.campaign.id', $fixture['campaign']->id)
            ->assertJsonPath('data.statistics.total_capacity', 5)
            ->assertJsonPath('data.restrictions.one_per_phone', true)
            ->assertJsonCount(1, 'data.vouchers');
    }

    public function test_redemption_log_supports_search_sort_and_filter(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 2);
        $voucher = $fixture['vouchers'][0];

        LaunchVoucherRedemption::query()->create([
            'launch_voucher_id' => $voucher->id,
            'campaign_id' => $fixture['campaign']->id,
            'customer_phone' => '08035001111',
            'customer_phone_normalized' => '08035001111',
            'status' => LaunchVoucherRedemption::STATUS_RESERVED,
            'discount_amount' => 500,
            'reserved_at' => now(),
        ]);

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/marketing/redemptions?search=08035001111&status=reserved&sort_by=reserved_at&sort_dir=desc')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.customer_phone', '08035001111')
            ->assertJsonPath('data.0.voucher_code', $voucher->code);
    }

    public function test_abuse_monitoring_endpoint_returns_category_summary(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 2);

        MarketingEvent::query()->create([
            'event_type' => MarketingEventService::TYPE_VOUCHER_BLOCKED,
            'launch_voucher_id' => $fixture['vouchers'][0]->id,
            'metadata' => ['reason' => 'phone', 'campaign_id' => $fixture['campaign']->id],
            'actor' => 'customer',
            'occurred_at' => now(),
        ]);

        MarketingEvent::query()->create([
            'event_type' => MarketingEventService::TYPE_VOUCHER_REJECTED,
            'launch_voucher_id' => $fixture['vouchers'][0]->id,
            'metadata' => ['code' => 'VOUCHER_EXPIRED', 'campaign_id' => $fixture['campaign']->id],
            'actor' => 'customer',
            'occurred_at' => now(),
        ]);

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/marketing/abuse')
            ->assertOk()
            ->assertJsonPath('data.summary.phone_blocked', 1)
            ->assertJsonPath('data.summary.expired_voucher', 1)
            ->assertJsonStructure(['data' => ['summary', 'blocked_trend', 'recent_events']]);
    }

    public function test_analytics_endpoint_returns_chart_series(): void
    {
        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/marketing/analytics')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'daily_redemptions',
                    'campaign_usage',
                    'network_distribution',
                    'blocked_trend',
                ],
            ]);
    }

    public function test_customer_lookup_finds_redemptions_by_phone_and_voucher_code(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 2);
        $voucher = $fixture['vouchers'][0];

        LaunchVoucherRedemption::query()->create([
            'launch_voucher_id' => $voucher->id,
            'campaign_id' => $fixture['campaign']->id,
            'customer_phone' => '08036002222',
            'customer_phone_normalized' => '08036002222',
            'status' => LaunchVoucherRedemption::STATUS_REDEEMED,
            'discount_amount' => 500,
            'reserved_at' => now(),
            'redeemed_at' => now(),
        ]);

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/marketing/customer-lookup?q=08036002222')
            ->assertOk()
            ->assertJsonPath('data.redemptions.0.customer_phone', '08036002222');

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/marketing/customer-lookup?q='.$voucher->code)
            ->assertOk()
            ->assertJsonPath('data.redemptions.0.voucher_code', $voucher->code);
    }

    public function test_extend_expiry_updates_campaign_and_vouchers(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 2);
        $newExpiry = now()->addMonths(2)->toIso8601String();

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->patchJson('/api/v1/ops/marketing/campaigns/'.$fixture['campaign']->id.'/expiry', [
                'expires_at' => $newExpiry,
            ])
            ->assertOk()
            ->assertJsonPath('data.campaign.id', $fixture['campaign']->id);

        $this->assertNotNull(LaunchVoucherCampaign::query()->find($fixture['campaign']->id)?->expires_at);
        $this->assertNotNull(LaunchVoucher::query()->find($fixture['vouchers'][0]->id)?->expires_at);
    }

    public function test_increase_capacity_updates_shared_campaign_and_voucher(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 2);

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->patchJson('/api/v1/ops/marketing/campaigns/'.$fixture['campaign']->id.'/capacity', [
                'max_redemptions' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.campaign.max_redemptions', 5)
            ->assertJsonPath('data.statistics.total_capacity', 5);

        $this->assertSame(5, LaunchVoucher::query()->find($fixture['vouchers'][0]->id)?->max_redemptions);
    }

    public function test_export_endpoints_remain_available(): void
    {
        $fixture = $this->createSharedLaunchVoucherCampaign(maxRedemptions: 2);

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/marketing/vouchers/export?campaign_id='.$fixture['campaign']->id)
            ->assertOk()
            ->assertJsonIsArray('data');

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->get('/api/v1/ops/marketing/vouchers/export.csv?campaign_id='.$fixture['campaign']->id)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
