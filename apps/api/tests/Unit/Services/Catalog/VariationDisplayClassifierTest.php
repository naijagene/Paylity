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

    public function test_xtratalk_is_hidden_case_insensitively(): void
    {
        $result = $this->classifier->classify(
            'MTN XTRATALK WEEKLY VOICE BUNDLE',
            500,
            'mtn-xtratalk-weekly',
        );

        $this->assertFalse($result['is_visible']);
        $this->assertSame('voice', $result['customer_category']);
    }

    public function test_xtratalk_is_hidden_from_variation_code_when_name_is_generic(): void
    {
        $result = $this->classifier->classify(
            'MTN 500MB',
            500,
            'mtn-xtratalk-1000',
        );

        $this->assertFalse($result['is_visible']);
        $this->assertSame('voice', $result['customer_category']);
    }

    public function test_xtradata_is_hidden(): void
    {
        $result = $this->classifier->classify(
            '9mobile XtraData Bundle',
            1500,
            'etisalat-xtradata-weekly',
        );

        $this->assertFalse($result['is_visible']);
        $this->assertSame('voice', $result['customer_category']);
    }

    public function test_minute_singular_is_hidden(): void
    {
        $result = $this->classifier->classify(
            'Airtel 120 Minute Voice Bundle',
            800,
            'airtel-voice-minute',
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

    public function test_gifting_keyword_is_hidden(): void
    {
        $result = $this->classifier->classify(
            'Glo Gifting Bundle 20GB',
            5000,
            'glo-gifting-20gb',
        );

        $this->assertFalse($result['is_visible']);
        $this->assertSame('sme', $result['customer_category']);
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

    public function test_enterprise_amount_is_detected_from_name_when_amount_is_null(): void
    {
        $result = $this->classifier->classify(
            'MTN N100,000 110GB Monthly Plan',
            null,
            'mtn-110gb-monthly',
        );

        $this->assertFalse($result['is_visible']);
    }

    #[DataProvider('visiblePlanProvider')]
    public function test_normal_data_plans_remain_visible(
        string $name,
        ?int $amount,
        string $variationCode,
        string $expectedDisplayName,
        string $expectedCategory,
    ): void {
        $result = $this->classifier->classify($name, $amount, $variationCode);

        $this->assertTrue($result['is_visible']);
        $this->assertSame($expectedDisplayName, $result['display_name']);
        $this->assertSame($expectedCategory, $result['customer_category']);
    }

    /**
     * @return array<string, array{0: string, 1: int|null, 2: string, 3: string, 4: string}>
     */
    public static function visiblePlanProvider(): array
    {
        return [
            '500MB monthly' => [
                'MTN Data - 500 Naira - 500MB - 30 days',
                500,
                'mtn-500mb-30days',
                '500MB - 30 Days',
                'monthly',
            ],
            '1GB weekly' => [
                'Airtel 1GB Weekly Plan - 7 Days',
                1000,
                'airtel-1gb-weekly',
                '1GB - 7 Days',
                'weekly',
            ],
            '5GB daily' => [
                'Glo 5GB Daily Bundle 1 Day',
                1500,
                'glo-5gb-daily',
                '5GB - 1 Day',
                'daily',
            ],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: int|null, 2: string, 3: string}>
     */
    public static function knownVtpassHiddenSamplesProvider(): array
    {
        return [
            'mtn xtratalk' => [
                'MTN N1000 Xtratalk Monthly Bundle',
                1000,
                'mtn-xtratalk-monthly',
                'voice',
            ],
            'airtel sme' => [
                'Airtel SME 75GB Data (90 Days)',
                50000,
                'airtel-sme-75gb',
                'sme',
            ],
            '9mobile corporate gifting' => [
                '9mobile Corporate Gifting 15GB',
                15000,
                'etisalat-corporate-gifting-15gb',
                'corporate',
            ],
            'mtn enterprise amount' => [
                'MTN 360GB Monthly Plan',
                100000,
                'mtn-360gb-monthly',
                'unknown',
            ],
        ];
    }

    #[DataProvider('knownVtpassHiddenSamplesProvider')]
    public function test_known_vtpass_catalog_samples_are_hidden(
        string $name,
        ?int $amount,
        string $variationCode,
        string $expectedCategory,
    ): void {
        $result = $this->classifier->classify($name, $amount, $variationCode);

        $this->assertFalse($result['is_visible'], $variationCode);
        $this->assertSame($expectedCategory, $result['customer_category']);
    }

    public function test_empty_variation_code_is_hidden(): void
    {
        $result = $this->classifier->classify('MTN 500MB', 500, '');

        $this->assertFalse($result['is_visible']);
    }

    public function test_empty_name_can_still_classify_from_variation_code(): void
    {
        $result = $this->classifier->classify('', 500, 'mtn-500mb-30days');

        $this->assertTrue($result['is_visible']);
    }
}
