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

    public function test_catalog_classify_force_overrides_persists_staging_hidden_rows(): void
    {
        $this->seedStagingRows();

        $this->artisan('paylity:catalog-classify', ['--force-overrides' => true])
            ->assertSuccessful();

        $hiddenCount = ProviderVariation::query()
            ->where('is_active', true)
            ->where('is_visible', false)
            ->count();

        $this->assertGreaterThan(0, $hiddenCount);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-xtratalk-300',
            'is_visible' => false,
            'customer_category' => 'voice',
        ]);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'airt-voice-100',
            'is_visible' => false,
            'customer_category' => 'voice',
        ]);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-360gb-sme-100000',
            'is_visible' => false,
            'customer_category' => 'sme',
        ]);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'mtn-4-5tb-450000',
            'is_visible' => false,
            'customer_category' => 'enterprise',
        ]);

        $this->assertDatabaseHas('provider_variations', [
            'variation_code' => 'airt-1000',
            'is_visible' => true,
            'display_name' => '1.5GB - 30 Days',
        ]);
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
    }

    private function seedStagingRows(): void
    {
        $rows = [
            ['service_name' => 'mtn', 'variation_code' => 'mtn-xtratalk-300', 'name' => 'MTN N200 Xtratalk - 3 days', 'amount' => 200],
            ['service_name' => 'airtel', 'variation_code' => 'airt-voice-100', 'name' => '600 Naira Voice Bundle', 'amount' => 100],
            ['service_name' => 'mtn', 'variation_code' => 'mtn-360gb-sme-100000', 'name' => 'MTN N100,000 360GB SME Mobile Data (3 Months)', 'amount' => 100000],
            ['service_name' => 'mtn', 'variation_code' => 'mtn-4-5tb-450000', 'name' => 'MTN N450,000 4.5TB Mobile Data (1 Year)', 'amount' => 450000],
            ['service_name' => 'airtel', 'variation_code' => 'airt-1000', 'name' => 'Airtel Data Bundle - 1,000 Naira - 1.5GB - 30 Days', 'amount' => 999],
        ];

        foreach ($rows as $row) {
            $service = ProviderService::query()
                ->where('category_key', 'data')
                ->where('service_name', $row['service_name'])
                ->firstOrFail();

            ProviderVariation::query()->create([
                'provider_service_id' => $service->id,
                'variation_code' => $row['variation_code'],
                'name' => $row['name'],
                'amount' => $row['amount'],
                'fixed_price' => true,
                'is_active' => true,
                'is_visible' => true,
                'display_override' => false,
            ]);
        }
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
