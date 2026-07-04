<?php

namespace App\Services\Catalog;

use App\Models\ProviderVariation;

class VariationClassificationService
{
    public function __construct(
        private readonly VariationDisplayClassifier $classifier,
    ) {
    }

    /**
     * @return array{
     *     total_processed: int,
     *     visible: int,
     *     hidden: int,
     *     voice_hidden: int,
     *     sme_corporate_hidden: int,
     *     enterprise_hidden: int,
     *     unknown: int
     * }
     */
    public function reclassifyAll(bool $forceOverrides = false): array
    {
        $summary = [
            'total_processed' => 0,
            'visible' => 0,
            'hidden' => 0,
            'voice_hidden' => 0,
            'sme_corporate_hidden' => 0,
            'enterprise_hidden' => 0,
            'unknown' => 0,
        ];

        ProviderVariation::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById(200, function ($variations) use (&$summary, $forceOverrides) {
                foreach ($variations as $variation) {
                    $result = $this->applyClassification($variation, $forceOverrides);
                    $summary['total_processed']++;

                    if ($result->isVisible()) {
                        $summary['visible']++;

                        if ($result->customerCategory === 'unknown') {
                            $summary['unknown']++;
                        }

                        continue;
                    }

                    $summary['hidden']++;
                    $this->incrementHiddenSummary($summary, $result);
                }
            });

        return $summary;
    }

    public function applyClassification(
        ProviderVariation $variation,
        bool $forceOverrides = false,
    ): VariationClassificationResult {
        if ($variation->display_override && ! $forceOverrides) {
            return VariationClassificationResult::fromModel($variation);
        }

        $result = $this->classifier->classify(
            name: (string) $variation->name,
            amount: $variation->amount,
            variationCode: (string) $variation->variation_code,
            extraSearchText: $this->buildExtraSearchText($variation),
        );

        $this->persistClassificationResult($variation, $result);

        return $result;
    }

    /**
     * @return list<array{variation_code: string, name: string, customer_category: string|null, amount: int|null}>
     */
    public function sampleHiddenVariations(int $limit = 5): array
    {
        return ProviderVariation::query()
            ->where('is_active', true)
            ->where('is_visible', false)
            ->orderBy('id')
            ->limit($limit)
            ->get(['variation_code', 'name', 'customer_category', 'amount'])
            ->map(fn (ProviderVariation $variation) => [
                'variation_code' => $variation->variation_code,
                'name' => $variation->name,
                'customer_category' => $variation->customer_category,
                'amount' => $variation->amount,
            ])
            ->values()
            ->all();
    }

    private function persistClassificationResult(
        ProviderVariation $variation,
        VariationClassificationResult $result,
    ): void {
        $attributes = $result->toPersistenceArray();

        ProviderVariation::query()
            ->whereKey($variation->getKey())
            ->update($attributes);

        $variation->fill($attributes);
        $variation->syncOriginal();
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function incrementHiddenSummary(array &$summary, VariationClassificationResult $result): void
    {
        if ($result->customerCategory === 'voice') {
            $summary['voice_hidden']++;

            return;
        }

        if (in_array($result->customerCategory, ['sme', 'corporate'], true)) {
            $summary['sme_corporate_hidden']++;

            return;
        }

        if ($result->customerCategory === 'enterprise') {
            $summary['enterprise_hidden']++;

            return;
        }

        $summary['unknown']++;
    }

    private function buildExtraSearchText(ProviderVariation $variation): string
    {
        $payload = is_array($variation->raw_payload) ? $variation->raw_payload : [];
        $parts = [];

        foreach (['name', 'variation_name', 'description', 'service_name', 'variation_code'] as $field) {
            $value = $payload[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $parts[] = trim($value);
            }
        }

        return implode(' ', $parts);
    }
}
