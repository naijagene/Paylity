<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsPlatformSettings;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class CorsHeadersTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPlatformSettings;
    use SeedsProductCatalog;

    private const OPERATOR_KEY = 'test-operator-key';

    private const OPS_ORIGIN = 'https://ops-paylity.vercel.app';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatformSettings();
        $this->seedProductCatalog();
        config([
            'services.operator.access_key' => self::OPERATOR_KEY,
            'cors.allowed_origins_extra' => self::OPS_ORIGIN,
        ]);
    }

    public function test_allowed_origin_receives_cors_headers_on_ops_dashboard_get(): void
    {
        $response = $this->withHeaders([
            'Origin' => self::OPS_ORIGIN,
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/dashboard');

        $response
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', self::OPS_ORIGIN)
            ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->assertHeader(
                'Access-Control-Allow-Headers',
                'Content-Type, Authorization, X-Requested-With, X-Operator-Key, X-CSRF-TOKEN, Accept',
            )
            ->assertHeader('Access-Control-Allow-Credentials', 'false')
            ->assertHeader('Vary', 'Origin');
    }

    public function test_options_preflight_returns_204_with_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => self::OPS_ORIGIN,
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'X-Operator-Key, Content-Type',
        ])->options('/api/v1/ops/dashboard');

        $response
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::OPS_ORIGIN)
            ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->assertHeader('Access-Control-Allow-Credentials', 'false')
            ->assertHeader('Vary', 'Origin');
    }

    public function test_disallowed_origin_does_not_receive_cors_headers(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://evil.example.com',
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/dashboard');

        $response
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
