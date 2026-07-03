<?php

namespace Tests\Feature\Api\V1;

use App\Models\ProviderService;
use App\Models\ProviderVariation;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProductCatalog();
    }

    public function test_catalog_products_endpoint_returns_categories_and_services(): void
    {
        $response = $this->getJson('/api/v1/catalog/products');

        $response
            ->assertOk()
            ->assertJsonPath('data.provider', 'vtpass')
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'airtime_networks',
                    'data_services',
                    'electricity_discos',
                ],
            ]);
    }

    public function test_catalog_products_endpoint_supports_category_filter(): void
    {
        $response = $this->getJson('/api/v1/catalog/products?category=data');

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => ['categories', 'data_services']])
            ->assertJsonMissingPath('data.airtime_networks');
    }

    public function test_checkout_rejects_unknown_data_variation(): void
    {
        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'data',
            'customer_phone' => '08031234567',
            'product_amount' => 350,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
                'variation_code' => 'invalid-variation-code',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INVALID_PRODUCT_VARIATION');
    }

    public function test_checkout_accepts_valid_data_variation(): void
    {
        $service = ProviderService::query()
            ->where('category_key', 'data')
            ->where('service_name', 'mtn')
            ->firstOrFail();

        ProviderVariation::query()->create([
            'provider_service_id' => $service->id,
            'variation_code' => 'mtn-10mb-100',
            'name' => 'MTN 10MB',
            'amount' => 100,
            'fixed_price' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'data',
            'customer_phone' => '08031234567',
            'product_amount' => 100,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
                'variation_code' => 'mtn-10mb-100',
                'service_id' => 'mtn-data',
                'plan_name' => 'MTN 10MB',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.product_type', 'data');

        $this->assertDatabaseHas('transactions', [
            'product_amount' => 100,
            'status' => 'created',
        ]);
    }

    public function test_catalog_sync_command_upserts_variations(): void
    {
        config([
            'services.vtpass.enabled' => true,
            'services.vtpass.base_url' => 'https://sandbox.vtpass.com',
            'services.vtpass.username' => 'vtpass-user',
            'services.vtpass.password' => 'vtpass-pass',
            'services.vtpass.api_key' => 'vtpass-api-key',
        ]);

        Http::fake([
            'https://sandbox.vtpass.com/api/service-variations*' => Http::response([
                'content' => [
                    'variations' => [
                        [
                            'variation_code' => 'mtn-10mb-100',
                            'name' => 'MTN 10MB',
                            'variation_amount' => '100',
                            'fixedPrice' => 'Yes',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('paylity:catalog-sync', ['provider' => 'vtpass'])
            ->assertSuccessful();

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-10mb-100',
            'is_active' => true,
        ]);
    }
}
