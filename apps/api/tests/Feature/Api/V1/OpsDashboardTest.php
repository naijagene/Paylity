<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsPlatformSettings;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class OpsDashboardTest extends TestCase
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
        config(['services.operator.access_key' => self::OPERATOR_KEY]);
    }

    public function test_dashboard_endpoint_requires_operator_key(): void
    {
        $this->getJson('/api/v1/ops/dashboard')->assertUnauthorized();
    }

    public function test_dashboard_endpoint_returns_command_center_snapshot(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/dashboard');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'enabled',
                    'refreshed_at',
                    'executive' => [
                        'revenue_today',
                        'transactions_today',
                        'success_rate',
                        'pending',
                        'failed',
                        'average_transaction',
                        'average_fulfillment_seconds',
                        'queue_size',
                        'api_health',
                    ],
                    'revenue' => [
                        'today',
                        'yesterday',
                        'week',
                        'month',
                    ],
                    'transactions' => [
                        'airtime',
                        'data',
                        'electricity',
                        'total',
                    ],
                    'providers',
                    'vtpass' => [
                        'environment',
                        'status',
                        'enabled',
                        'auto_fulfill',
                        'live_safety_mode',
                        'live_test_max_amount',
                        'balance',
                        'product_readiness',
                    ],
                    'fraud',
                    'alerts',
                    'platform',
                ],
            ])
            ->assertJsonPath('data.enabled', true);
    }
}
