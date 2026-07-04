<?php

namespace Tests\Unit\Services\Catalog;

use App\Services\Catalog\VariationDisplayClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VariationDisplayClassifierTest extends TestCase
{
    private VariationDisplayClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new VariationDisplayClassifier;
    }

    public function test_voice_bundle_is_hidden(): void
    {
        $result = $this->classifier->classify(
            'MTN N1000 Xtratalk Monthly Bundle',
            1000,
            'mtn-xtratalk-monthly',
        );

        $this->assertFalse($result['is_visible']);
        $this->assertSame('voice', $result['customer_category']);
    }

    public function test_xtratalk_is_hidden(): void
    {
        $result = $this->classifier->classify(
            'MTN Xtratalk Weekly Voice Bundle',
            500,
            'mtn-xtratalk-weekly',
        );

        $this->assertFalse($result['is_visible']);
        $this->assertSame('voice', $result['customer_category']);
    }

    public function test_sme_corporate_bundle_is_hidden(): void
    {
        $result = $this->classifier->classify(
            'MTN N100,000 360GB SME Mobile Data (3 Months)',
            100000,
            'mtn-sme-360gb',
        );

        $this->assertFalse($result['is_visible']);
        $this->assertSame('sme', $result['customer_category']);
    }

    public function test_corporate_gifting_is_hidden(): void
    {
        $result = $this->classifier->classify(
            'Airtel Corporate Gifting 50GB Plan',
            25000,
            'airtel-corporate-gifting',
        );

        $this->assertFalse($result['is_visible']);
        $this->assertSame('corporate', $result['customer_category']);
    }

    public function test_enterprise_amount_above_threshold_is_hidden(): void
    {
        $result = $this->classifier->classify(
            'MTN 500GB Premium Data Bundle',
            75000,
            'mtn-500gb-premium',
        );

        $this->assertFalse($result['is_visible']);
    }

    #[DataProvider('visiblePlanProvider')]
    public function test_normal_data_plans_remain_visible(
        string $name,
        ?int $amount,
        string $expectedDisplayName,
        string $expectedCategory,
    ): void {
        $result = $this->classifier->classify($name, $amount, 'sample-code');

        $this->assertTrue($result['is_visible']);
        $this->assertSame($expectedDisplayName, $result['display_name']);
        $this->assertSame($expectedCategory, $result['customer_category']);
    }

    /**
     * @return array<string, array{0: string, 1: int|null, 2: string, 3: string}>
     */
    public static function visiblePlanProvider(): array
    {
        return [
            '500MB monthly' => [
                'MTN Data - 500 Naira - 500MB - 30 days',
                500,
                '500MB - 30 Days',
                'monthly',
            ],
            '1GB weekly' => [
                'Airtel 1GB Weekly Plan - 7 Days',
                1000,
                '1GB - 7 Days',
                'weekly',
            ],
            '5GB daily' => [
                'Glo 5GB Daily Bundle 1 Day',
                1500,
                '5GB - 1 Day',
                'daily',
            ],
        ];
    }

    public function test_empty_name_is_hidden(): void
    {
        $result = $this->classifier->classify('', 500, 'missing-name');

        $this->assertFalse($result['is_visible']);
    }
}
