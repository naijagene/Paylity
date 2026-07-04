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

    public function test_result_uses_persistence_contract_keys(): void
    {
        $result = $this->classifier->classify(
            'MTN Data - 500 Naira - 500MB - 30 days',
            500,
            'mtn-500mb-30days',
        );

        $this->assertSame('500MB - 30 Days', $result->displayName);
        $this->assertTrue($result->isVisible());
        $this->assertSame('monthly', $result->customerCategory);
        $this->assertSame('30 Days', $result->validityLabel);
        $this->assertSame('500MB', $result->dataSizeLabel);
        $this->assertNotNull($result->sortOrder);
    }

    #[DataProvider('stagingRegressionProvider')]
    public function test_staging_regression_samples(
        string $variationCode,
        string $name,
        ?int $amount,
        bool $expectedVisible,
        string $expectedCategory,
        ?string $expectedDisplaySubstring = null,
    ): void {
        $result = $this->classifier->classify($name, $amount, $variationCode);

        $this->assertSame($expectedVisible, $result->isVisible(), $variationCode);
        $this->assertSame($expectedCategory, $result->customerCategory, $variationCode);

        if ($expectedDisplaySubstring !== null) {
            $this->assertNotNull($result->displayName);
            $this->assertStringContainsString(
                $expectedDisplaySubstring,
                $result->displayName,
                $variationCode,
            );
        }
    }

    /**
     * @return array<string, array{
     *     0: string,
     *     1: string,
     *     2: int|null,
     *     3: bool,
     *     4: string,
     *     5?: string|null
     * }>
     */
    public static function stagingRegressionProvider(): array
    {
        return [
            'mtn xtratalk staging row' => [
                'mtn-xtratalk-300',
                'MTN N200 Xtratalk - 3 days',
                200,
                false,
                'voice',
                null,
            ],
            'airt voice staging row' => [
                'airt-voice-100',
                '600 Naira Voice Bundle',
                100,
                false,
                'voice',
                null,
            ],
            'mtn sme staging row' => [
                'mtn-360gb-sme-100000',
                'MTN N100,000 360GB SME Mobile Data (3 Months)',
                100000,
                false,
                'sme',
                null,
            ],
            'mtn enterprise staging row' => [
                'mtn-4-5tb-450000',
                'MTN N450,000 4.5TB Mobile Data (1 Year)',
                450000,
                false,
                'enterprise',
                null,
            ],
            'airt visible staging row' => [
                'airt-1000',
                'Airtel Data Bundle - 1,000 Naira - 1.5GB - 30 Days',
                999,
                true,
                'monthly',
                '1.5GB',
            ],
        ];
    }

    public function test_voice_bundle_is_hidden(): void
    {
        $result = $this->classifier->classify(
            'MTN N1000 Xtratalk Monthly Bundle',
            1000,
            'mtn-xtratalk-monthly',
        );

        $this->assertTrue($result->isHidden());
        $this->assertSame('voice', $result->customerCategory);
    }

    public function test_empty_variation_code_is_hidden(): void
    {
        $result = $this->classifier->classify('MTN 500MB', 500, '');

        $this->assertTrue($result->isHidden());
    }

    public function test_empty_name_can_still_classify_from_variation_code(): void
    {
        $result = $this->classifier->classify('', 500, 'mtn-500mb-30days');

        $this->assertTrue($result->isVisible());
    }

    public function test_airt_visible_row_includes_validity_in_display_name(): void
    {
        $result = $this->classifier->classify(
            'Airtel Data Bundle - 1,000 Naira - 1.5GB - 30 Days',
            999,
            'airt-1000',
        );

        $this->assertTrue($result->isVisible());
        $this->assertSame('1.5GB - 30 Days', $result->displayName);
    }
}
