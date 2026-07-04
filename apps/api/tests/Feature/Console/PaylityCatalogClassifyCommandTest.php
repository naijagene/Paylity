<?php

namespace Tests\Feature\Console;

use App\Models\ProviderService;
use App\Models\ProviderVariation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class PaylityCatalogClassifyCommandTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProductCatalog();
    }

    public function test_catalog_classify_hides_known_vtpass_samples_for_mtn_airtel_and_9mobile(): void
    {
        $this->seedKnownVtpassSamples();

        $this->artisan('paylity:catalog-classify')
            ->assertSuccessful();

        $hiddenCount = ProviderVariation::query()
            ->where('is_active', true)
            ->where('is_visible', false)
            ->count();

        $visibleCount = ProviderVariation::query()
            ->where('is_active', true)
            ->where('is_visible', true)
            ->count();

        $this->assertGreaterThan(0, $hiddenCount);
        $this->assertGreaterThan(0, $visibleCount);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-xtratalk-monthly',
            'is_visible' => false,
            'customer_category' => 'voice',
        ]);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'airtel-sme-75gb',
            'is_visible' => false,
            'customer_category' => 'sme',
        ]);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'etisalat-corporate-gifting-15gb',
            'is_visible' => false,
            'customer_category' => 'corporate',
        ]);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-500mb-30days',
            'is_visible' => true,
            'display_name' => '500MB - 30 Days',
        ]);
    }

    private function seedKnownVtpassSamples(): void
    {
        $samples = [
            ['service_name' => 'mtn', 'variation_code' => 'mtn-500mb-30days', 'name' => 'MTN Data - 500 Naira - 500MB - 30 days', 'amount' => 500],
            ['service_name' => 'mtn', 'variation_code' => 'mtn-xtratalk-monthly', 'name' => 'MTN N1000 Xtratalk Monthly Bundle', 'amount' => 1000],
            ['service_name' => 'mtn', 'variation_code' => 'mtn-360gb-monthly', 'name' => 'MTN N100,000 360GB Monthly Plan', 'amount' => 100000],
            ['service_name' => 'airtel', 'variation_code' => 'airtel-1gb-weekly', 'name' => 'Airtel 1GB Weekly Plan - 7 Days', 'amount' => 1000],
            ['service_name' => 'airtel', 'variation_code' => 'airtel-sme-75gb', 'name' => 'Airtel SME 75GB Data (90 Days)', 'amount' => 50000],
            ['service_name' => '9mobile', 'variation_code' => 'etisalat-corporate-gifting-15gb', 'name' => '9mobile Corporate Gifting 15GB', 'amount' => 15000],
            ['service_name' => '9mobile', 'variation_code' => 'etisalat-xtradata-weekly', 'name' => '9mobile XtraData Weekly Bundle', 'amount' => 1200],
        ];

        foreach ($samples as $sample) {
            $service = ProviderService::query()
                ->where('category_key', 'data')
                ->where('service_name', $sample['service_name'])
                ->firstOrFail();

            ProviderVariation::query()->create([
                'provider_service_id' => $service->id,
                'variation_code' => $sample['variation_code'],
                'name' => $sample['name'],
                'amount' => $sample['amount'],
                'fixed_price' => true,
                'is_active' => true,
                'is_visible' => true,
                'display_override' => false,
                'raw_payload' => [
                    'variation_code' => $sample['variation_code'],
                    'name' => $sample['name'],
                    'variation_amount' => (string) $sample['amount'],
                ],
            ]);
        }
    }
}
