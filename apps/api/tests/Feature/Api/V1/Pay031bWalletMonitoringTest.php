<?php

namespace Tests\Feature\Api\V1;

use App\Services\Fulfillment\VtpassWalletBalanceService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsPlatformSettings;
use Tests\TestCase;

class Pay031bWalletMonitoringTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPlatformSettings;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatformSettings();

        $this->withIntegratedFeatureFlags([
            'FEATURE_VTPASS' => true,
        ]);

        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.base_url' => 'https://vtpass.com',
            'services.vtpass.username' => 'live-user',
            'services.vtpass.password' => 'live-pass',
            'services.vtpass.api_key' => 'live-api-key',
            'services.vtpass.public_key' => 'PK_live_public',
            'services.operator.access_key' => self::OPERATOR_KEY,
        ]);

        Cache::flush();
    }

    public function test_wallet_balance_is_cached_for_configured_interval(): void
    {
        Http::fake([
            'https://vtpass.com/api/balance' => Http::response([
                'code' => 1,
                'contents' => ['balance' => 750000],
            ]),
        ]);

        $service = app(VtpassWalletBalanceService::class);
        $service->snapshot();
        $service->snapshot();

        Http::assertSentCount(1);
    }

    public function test_refresh_endpoint_bypasses_cache(): void
    {
        Http::fake([
            'https://vtpass.com/api/balance' => Http::response([
                'code' => 1,
                'contents' => ['balance' => 450000],
            ]),
        ]);

        app(VtpassWalletBalanceService::class)->snapshot();

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->postJson('/api/v1/ops/vtpass/wallet/refresh')
            ->assertOk()
            ->assertJsonPath('data.balance', 450000)
            ->assertJsonPath('data.cached', false);

        Http::assertSentCount(2);
    }

    public function test_low_balance_warning_alert_is_created(): void
    {
        Http::fake([
            'https://vtpass.com/api/balance' => Http::response([
                'code' => 1,
                'contents' => ['balance' => 250000],
            ]),
        ]);

        app(\App\Services\Platform\SystemSettingsService::class)->setMany([
            SystemSettingKeys::WALLET_LOW_BALANCE_THRESHOLD => 500000,
            SystemSettingKeys::WALLET_CRITICAL_BALANCE_THRESHOLD => 100000,
        ]);

        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.vtpass.balance.health', 'warning');

        $alerts = collect($response->json('data.alerts'));
        $this->assertNotNull($alerts->firstWhere('code', 'VTPASS_WALLET_LOW'));
    }

    public function test_critical_balance_alert_is_created(): void
    {
        Http::fake([
            'https://vtpass.com/api/balance' => Http::response([
                'code' => 1,
                'contents' => ['balance' => 50000],
            ]),
        ]);

        $response = $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.vtpass.balance.health', 'critical');

        $alerts = collect($response->json('data.alerts'));
        $this->assertNotNull($alerts->firstWhere('code', 'VTPASS_WALLET_CRITICAL'));
    }

    public function test_monitoring_summary_includes_wallet_block(): void
    {
        Http::fake([
            'https://vtpass.com/api/balance' => Http::response([
                'code' => 1,
                'contents' => ['balance' => 900000],
            ]),
        ]);

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/monitoring')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'wallet' => ['available', 'balance', 'health', 'checked_at'],
                    'vtpass' => ['status', 'enabled', 'environment', 'balance'],
                ],
            ]);
    }

    public function test_daily_reconciliation_report_includes_wallet_summary(): void
    {
        Http::fake([
            'https://vtpass.com/api/balance' => Http::response([
                'code' => 1,
                'contents' => ['balance' => 800000],
            ]),
        ]);

        app(VtpassWalletBalanceService::class)->refresh();

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/reports/daily-reconciliation')
            ->assertOk()
            ->assertJsonPath('data.wallet.opening_balance', 800000)
            ->assertJsonPath('data.wallet.closing_balance', 800000);
    }
}
