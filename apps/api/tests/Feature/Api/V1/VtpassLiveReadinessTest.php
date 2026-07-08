<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsPlatformSettings;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class VtpassLiveReadinessTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPlatformSettings;
    use SeedsProductCatalog;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatformSettings();
        $this->seedProductCatalog();

        $this->withIntegratedFeatureFlags([
            'FEATURE_VTPASS' => true,
            'FEATURE_VTPASS_AUTO_FULFILL' => false,
        ]);

        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.environment' => 'production',
            'services.vtpass.base_url' => 'https://vtpass.com',
            'services.vtpass.username' => 'live-user',
            'services.vtpass.password' => 'live-pass',
            'services.vtpass.api_key' => 'live-api-key',
            'services.vtpass.public_key' => 'PK_live_public',
            'services.vtpass.secret_key' => 'SK_live_secret',
            'services.operator.access_key' => self::OPERATOR_KEY,
        ]);
    }

    public function test_production_env_config_validation_passes_for_vtpass(): void
    {
        $results = app(\App\Support\Platform\PaylityEnvironmentValidator::class)->validate();

        $environment = collect($results)->firstWhere('check', 'VTPass environment');
        $credentials = collect($results)->firstWhere('check', 'VTPass');

        $this->assertSame('PASS', $environment['status'] ?? null);
        $this->assertSame('PASS', $credentials['status'] ?? null);
        $this->assertStringContainsString('VTPASS_ENV=production', (string) ($environment['detail'] ?? ''));
    }

    public function test_dashboard_exposes_vtpass_environment_status_and_safety_mode(): void
    {
        Http::fake([
            'https://vtpass.com/api/balance' => Http::response([
                'code' => 1,
                'contents' => ['balance' => 1500.25],
            ]),
        ]);

        app(\App\Services\Platform\SystemSettingsService::class)->set(
            SystemSettingKeys::VTPASS_LIVE_SAFETY_MODE,
            true,
        );

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('data.vtpass.environment', 'production')
            ->assertJsonPath('data.vtpass.base_url_host', 'vtpass.com')
            ->assertJsonPath('data.vtpass.live_safety_mode', true)
            ->assertJsonPath('data.vtpass.live_test_max_amount', 500)
            ->assertJsonPath('data.vtpass.balance.available', true)
            ->assertJsonPath('data.vtpass.product_readiness.airtime.ready', true)
            ->assertJsonPath('data.vtpass.product_readiness.data.ready', false);
    }

    public function test_live_safety_mode_blocks_ops_fulfillment_above_threshold(): void
    {
        app(\App\Services\Platform\SystemSettingsService::class)->setMany([
            SystemSettingKeys::VTPASS_LIVE_SAFETY_MODE => true,
            SystemSettingKeys::VTPASS_LIVE_TEST_MAX_AMOUNT => 500,
        ]);

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260708-LIVE01',
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 1100,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'verified_phone' => false,
        ]);

        Http::fake();

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/transactions/'.$transaction->reference.'/fulfill');

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VTPASS_LIVE_SAFETY_LIMIT');

        Http::assertNothingSent();
    }

    public function test_disabled_vtpass_product_blocks_fulfillment(): void
    {
        config(['services.vtpass.environment' => 'sandbox']);

        app(\App\Services\Platform\FeatureFlagService::class)->set(
            FeatureFlagKeys::PROVIDER_VTPASS_DATA_ENABLED,
            false,
        );

        $transaction = Transaction::query()->create([
            'reference' => 'PYL-20260708-DATA01',
            'product_type' => 'data',
            'customer_phone' => '08031234567',
            'product_amount' => 100,
            'convenience_fee' => 100,
            'gateway_fee' => 0,
            'payable_amount' => 200,
            'currency' => 'NGN',
            'status' => TransactionStatus::PAYMENT_SUCCESS,
            'request_payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
                'variation_code' => 'mtn-1gb-daily',
                'service_id' => 'mtn-data',
            ],
            'verified_phone' => false,
        ]);

        Http::fake();

        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->postJson('/api/v1/ops/transactions/'.$transaction->reference.'/fulfill');

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'VTPASS_PRODUCT_NOT_READY');

        Http::assertNothingSent();
    }
}
