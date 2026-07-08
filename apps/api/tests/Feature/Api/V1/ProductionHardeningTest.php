<?php

namespace Tests\Feature\Api\V1;

use App\Models\SystemSetting;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedProductCatalog();
        config(['services.operator.access_key' => self::OPERATOR_KEY]);
    }

    public function test_api_responses_include_security_headers(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_platform_status_endpoint_reports_checkout_availability(): void
    {
        $response = $this->getJson('/api/v1/platform/status');

        $response
            ->assertOk()
            ->assertJsonPath('data.checkout_enabled', true)
            ->assertJsonPath('data.maintenance_mode', false)
            ->assertJsonPath('data.incident_mode', false);
    }

    public function test_incident_mode_blocks_checkout(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => SystemSettingKeys::INCIDENT_MODE],
            ['value' => '1', 'type' => 'boolean'],
        );
        app(\App\Services\Platform\SystemSettingsService::class)->forgetCache();

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INCIDENT_MODE');
    }

    public function test_maintenance_mode_blocks_checkout(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => SystemSettingKeys::MAINTENANCE_MODE],
            ['value' => '1', 'type' => 'boolean'],
        );
        app(\App\Services\Platform\SystemSettingsService::class)->forgetCache();

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'MAINTENANCE_MODE');
    }

    public function test_ops_reports_endpoints_require_operator_key(): void
    {
        $this->getJson('/api/v1/ops/reports/daily-reconciliation')
            ->assertUnauthorized();

        $this->getJson('/api/v1/ops/reports/failed-transactions')
            ->assertUnauthorized();

        $this->getJson('/api/v1/ops/reports/settlement-summary')
            ->assertUnauthorized();

        $this->getJson('/api/v1/ops/reports/retry-summary')
            ->assertUnauthorized();
    }

    public function test_ops_daily_reconciliation_report_returns_summary(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/reports/daily-reconciliation');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'date',
                    'total_transactions',
                    'successful_payments',
                    'payment_failed',
                    'fulfillment_failed',
                    'fulfilled',
                    'pending_fulfillment',
                    'gross_revenue',
                    'success_rate',
                ],
            ]);
    }

    public function test_ops_monitoring_includes_queue_metrics(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/monitoring');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'queue' => [
                        'connection',
                        'pending_jobs',
                        'failed_jobs',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_environment_validator_is_used_by_preflight_command(): void
    {
        config([
            'app.env' => 'staging',
            'app.debug' => false,
            'app.url' => 'https://api-staging.paylity.ng',
            'app.frontend_url' => 'https://staging.paylity.ng',
            'app.version' => '1.0.0-rc1',
            'app.build' => '2026.07.03-rc1',
            'services.operator.access_key' => self::OPERATOR_KEY,
            'services.paystack.enabled' => false,
            'services.vtpass.enabled' => false,
        ]);

        $this->artisan('paylity:preflight')
            ->assertExitCode(0);
    }
}
