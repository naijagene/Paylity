<?php

namespace Tests\Feature\Api\V1;

use App\Models\ProviderService;
use App\Models\ProviderVariation;
use App\Services\Catalog\VariationClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class ProductCatalogFilteringTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProductCatalog();
    }

    public function test_public_catalog_only_returns_visible_plans(): void
    {
        $service = $this->mtnDataService();

        $this->createVariation($service, [
            'variation_code' => 'mtn-visible-plan',
            'name' => 'MTN Data - 500 Naira - 500MB - 30 days',
            'amount' => 500,
            'is_visible' => true,
            'display_name' => '500MB - 30 Days',
        ]);

        $this->createVariation($service, [
            'variation_code' => 'mtn-hidden-voice',
            'name' => 'MTN N1000 Xtratalk Monthly Bundle',
            'amount' => 1000,
            'is_visible' => false,
            'display_name' => 'MTN N1000 Xtratalk Monthly Bundle',
        ]);

        $response = $this->getJson('/api/v1/catalog/products?category=data');

        $response
            ->assertOk()
            ->assertJsonPath('data.catalog_meta.total_variations', 2)
            ->assertJsonPath('data.catalog_meta.visible_variations', 1)
            ->assertJsonPath('data.catalog_meta.hidden_variations', 1);

        $variationCodes = $this->mtnVariationCodes($response->json('data'));

        $this->assertSame(['mtn-visible-plan'], $variationCodes);
    }

    public function test_include_hidden_is_ignored_without_operator_key(): void
    {
        $service = $this->mtnDataService();

        $this->createVariation($service, [
            'variation_code' => 'mtn-hidden-voice',
            'name' => 'MTN N1000 Xtratalk Monthly Bundle',
            'amount' => 1000,
            'is_visible' => false,
        ]);

        $response = $this->getJson('/api/v1/catalog/products?category=data&include_hidden=1');

        $response->assertOk();
        $this->assertSame([], $this->mtnVariationCodes($response->json('data')));
    }

    public function test_include_hidden_exposes_hidden_plans_for_operator(): void
    {
        config(['services.operator.access_key' => 'test-operator-key']);

        $service = $this->mtnDataService();

        $this->createVariation($service, [
            'variation_code' => 'mtn-hidden-voice',
            'name' => 'MTN N1000 Xtratalk Monthly Bundle',
            'amount' => 1000,
            'is_visible' => false,
        ]);

        $response = $this->getJson(
            '/api/v1/catalog/products?category=data&include_hidden=1',
            ['X-Operator-Key' => 'test-operator-key'],
        );

        $response->assertOk();

        $variationCodes = $this->mtnVariationCodes($response->json('data'));

        $this->assertContains('mtn-hidden-voice', $variationCodes);
    }

    public function test_checkout_rejects_hidden_data_variation(): void
    {
        $service = $this->mtnDataService();

        $this->createVariation($service, [
            'variation_code' => 'mtn-hidden-voice',
            'name' => 'MTN N1000 Xtratalk Monthly Bundle',
            'amount' => 1000,
            'is_visible' => false,
            'fixed_price' => true,
        ]);

        $response = $this->postJson('/api/v1/checkout/initialize', [
            'product_type' => 'data',
            'customer_phone' => '08031234567',
            'product_amount' => 1000,
            'payload' => [
                'network' => 'MTN',
                'recipient_phone' => '08031234567',
                'variation_code' => 'mtn-hidden-voice',
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'INVALID_PRODUCT_VARIATION');
    }

    public function test_display_override_is_preserved_during_reclassification(): void
    {
        $service = $this->mtnDataService();

        $variation = $this->createVariation($service, [
            'variation_code' => 'mtn-custom-plan',
            'name' => 'MTN N1000 Xtratalk Monthly Bundle',
            'amount' => 1000,
            'display_name' => 'Custom Customer Plan',
            'is_visible' => true,
            'display_override' => true,
        ]);

        app(VariationClassificationService::class)->reclassifyAll();

        $variation->refresh();

        $this->assertTrue($variation->display_override);
        $this->assertSame('Custom Customer Plan', $variation->display_name);
        $this->assertTrue($variation->is_visible);
    }

    public function test_catalog_classify_command_reports_summary(): void
    {
        $service = $this->mtnDataService();

        $this->createVariation($service, [
            'variation_code' => 'mtn-visible-plan',
            'name' => 'MTN Data - 500 Naira - 500MB - 30 days',
            'amount' => 500,
        ]);

        $this->createVariation($service, [
            'variation_code' => 'mtn-hidden-voice',
            'name' => 'MTN N1000 Xtratalk Monthly Bundle',
            'amount' => 1000,
        ]);

        $this->artisan('paylity:catalog-classify')
            ->assertSuccessful();

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-visible-plan',
            'is_visible' => true,
            'display_name' => '500MB - 30 Days',
        ]);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-hidden-voice',
            'is_visible' => false,
        ]);
    }

    public function test_catalog_sync_applies_classification(): void
    {
        $this->withIntegratedFeatureFlags(['FEATURE_VTPASS' => true]);

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
                            'variation_code' => 'mtn-500mb-30days',
                            'name' => 'MTN Data - 500 Naira - 500MB - 30 days',
                            'variation_amount' => '500',
                            'fixedPrice' => 'Yes',
                        ],
                        [
                            'variation_code' => 'mtn-xtratalk',
                            'name' => 'MTN N1000 Xtratalk Monthly Bundle',
                            'variation_amount' => '1000',
                            'fixedPrice' => 'Yes',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->artisan('paylity:catalog-sync', ['provider' => 'vtpass'])
            ->assertSuccessful();

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-500mb-30days',
            'is_visible' => true,
            'display_name' => '500MB - 30 Days',
        ]);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-xtratalk',
            'is_visible' => false,
        ]);
    }

    private function mtnDataService(): ProviderService
    {
        return ProviderService::query()
            ->where('category_key', 'data')
            ->where('service_name', 'mtn')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createVariation(ProviderService $service, array $attributes): ProviderVariation
    {
        return ProviderVariation::query()->create(array_merge([
            'provider_service_id' => $service->id,
            'fixed_price' => true,
            'is_active' => true,
            'is_visible' => true,
            'display_override' => false,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $catalogData
     * @return list<string>
     */
    private function mtnVariationCodes(array $catalogData): array
    {
        $service = collect($catalogData['data_services'] ?? [])
            ->firstWhere('service_name', 'mtn');

        return collect($service['variations'] ?? [])
            ->pluck('variation_code')
            ->all();
    }
}
