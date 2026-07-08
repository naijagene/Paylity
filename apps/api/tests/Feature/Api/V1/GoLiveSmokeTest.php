<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsPlatformSettings;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class GoLiveSmokeTest extends TestCase
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

    public function test_smoke_health_monitoring_and_platform_status(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
        $this->getJson('/api/v1/platform/status')->assertOk();

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/monitoring')
            ->assertOk();

        $this->withHeaders(['X-Operator-Key' => self::OPERATOR_KEY])
            ->getJson('/api/v1/ops/summary')
            ->assertOk();
    }

    public function test_smoke_catalog_and_checkout_paths(): void
    {
        $this->getJson('/api/v1/catalog/products')->assertOk();
        $this->getJson('/api/v1/catalog/products?category=airtime')->assertOk();
        $this->getJson('/api/v1/catalog/products?category=data')->assertOk();
        $this->getJson('/api/v1/catalog/products?category=electricity')->assertOk();

        $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'airtime',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
            ],
        ])->assertCreated();
    }

    public function test_smoke_otp_endpoints_are_available(): void
    {
        $this->postJson('/api/v1/otp/request', [
            'phone' => '08031234567',
            'purpose' => 'checkout',
        ])->assertCreated();
    }

    public function test_smoke_transaction_history_and_receipt_verification_routes(): void
    {
        $this->getJson('/api/v1/transactions?phone=08031234567')->assertOk();
        $this->getJson('/api/v1/receipts/verify/invalid-token')->assertNotFound();
    }

    public function test_smoke_ops_reports_and_settings(): void
    {
        $headers = ['X-Operator-Key' => self::OPERATOR_KEY];

        $this->withHeaders($headers)->getJson('/api/v1/settings')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/feature-flags')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/ops/reports/daily-reconciliation')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/ops/reports/failed-transactions')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/ops/reports/settlement-summary')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/ops/reports/retry-summary')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/ops/dashboard')->assertOk();
    }
}
