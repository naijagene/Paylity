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
                    $this->applyClassification($variation, $forceOverrides);
                    $summary['total_processed']++;

                    if ($variation->is_visible) {
                        $summary['visible']++;

                        if ($variation->customer_category === 'unknown') {
                            $summary['unknown']++;
                        }

                        continue;
                    }

                    $summary['hidden']++;

                    if ($variation->customer_category === 'voice') {
                        $summary['voice_hidden']++;
                    } elseif (in_array($variation->customer_category, ['sme', 'corporate'], true)) {
                        $summary['sme_corporate_hidden']++;
                    } elseif (($variation->amount ?? 0) > 50000) {
                        $summary['enterprise_hidden']++;
                    } else {
                        $summary['unknown']++;
                    }
                }
            });

        return $summary;
    }

    public function applyClassification(
        ProviderVariation $variation,
        bool $forceOverrides = false,
    ): ProviderVariation {
        if ($variation->display_override && ! $forceOverrides) {
            return $variation;
        }

        $classified = $this->classifier->classify(
            name: (string) $variation->name,
            amount: $variation->amount,
            variationCode: (string) $variation->variation_code,
        );

        $variation->fill([
            'display_name' => $classified['display_name'],
            'is_visible' => $classified['is_visible'],
            'customer_category' => $classified['customer_category'],
            'validity_label' => $classified['validity_label'],
            'data_size_label' => $classified['data_size_label'],
            'sort_order' => $classified['sort_order'],
        ]);

        $variation->save();

        return $variation;
    }
}
