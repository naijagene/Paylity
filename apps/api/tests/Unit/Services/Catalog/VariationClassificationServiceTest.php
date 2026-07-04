<?php

namespace Tests\Unit\Services\Catalog;

use App\Models\ProviderService;
use App\Models\ProviderVariation;
use App\Services\Catalog\VariationClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class VariationClassificationServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedProductCatalog();
    }

    public function test_apply_classification_persists_is_visible_false(): void
    {
        $service = ProviderService::query()
            ->where('category_key', 'data')
            ->where('service_name', 'mtn')
            ->firstOrFail();

        $variation = ProviderVariation::query()->create([
            'provider_service_id' => $service->id,
            'variation_code' => 'mtn-xtratalk-300',
            'name' => 'MTN N200 Xtratalk - 3 days',
            'amount' => 200,
            'fixed_price' => true,
            'is_active' => true,
            'is_visible' => true,
            'display_override' => false,
        ]);

        $result = app(VariationClassificationService::class)->applyClassification($variation, true);

        $this->assertTrue($result->isHidden());
        $this->assertSame('voice', $result->customerCategory);

        $variation->refresh();

        $this->assertFalse($variation->is_visible);
        $this->assertSame('voice', $variation->customer_category);
    }

    public function test_reclassify_all_counts_hidden_from_classification_result(): void
    {
        $this->seedStagingRows();

        $summary = app(VariationClassificationService::class)->reclassifyAll(true);

        $this->assertGreaterThan(0, $summary['hidden']);
        $this->assertGreaterThan(0, $summary['voice_hidden']);
        $this->assertGreaterThan(0, $summary['enterprise_hidden']);
        $this->assertGreaterThan(0, $summary['visible']);
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
}
