<?php

namespace App\Services\Catalog;

use App\Exceptions\VTPassException;
use App\Models\ProviderService;
use App\Models\ProviderVariation;
use App\Services\Fulfillment\VTPassService;
use Illuminate\Support\Facades\DB;

class VTPassCatalogSyncService
{
    public function __construct(
        private readonly VTPassService $vtpassService,
        private readonly ProductCatalogService $productCatalogService,
        private readonly VariationClassificationService $classificationService,
    ) {
    }

    /**
     * @return array{
     *     services_synced: int,
     *     variations_added: int,
     *     variations_updated: int,
     *     variations_deactivated: int,
     *     failures: list<string>
     * }
     */
    public function syncDataVariations(): array
    {
        $summary = [
            'services_synced' => 0,
            'variations_added' => 0,
            'variations_updated' => 0,
            'variations_deactivated' => 0,
            'failures' => [],
        ];

        $services = ProviderService::query()
            ->where('provider', ProductCatalogService::PROVIDER_VTPASS)
            ->where('category_key', 'data')
            ->where('is_active', true)
            ->orderBy('service_id')
            ->get();

        foreach ($services as $service) {
            try {
                $response = $this->vtpassService->getServiceVariations($service->service_id);
                $variations = $this->extractVariations($response);
                $syncedCodes = [];

                DB::transaction(function () use (
                    $service,
                    $variations,
                    &$syncedCodes,
                    &$summary,
                ) {
                    foreach ($variations as $variation) {
                        $variationCode = (string) ($variation['variation_code'] ?? '');

                        if ($variationCode === '') {
                            continue;
                        }

                        $syncedCodes[] = $variationCode;
                        $amount = $this->parseAmount($variation);
                        $fixedPrice = filter_var(
                            $variation['fixedPrice'] ?? $variation['fixed_price'] ?? false,
                            FILTER_VALIDATE_BOOL,
                        );

                        $existing = ProviderVariation::query()
                            ->where('provider_service_id', $service->id)
                            ->where('variation_code', $variationCode)
                            ->first();

                        $providerVariation = ProviderVariation::query()->updateOrCreate(
                            [
                                'provider_service_id' => $service->id,
                                'variation_code' => $variationCode,
                            ],
                            [
                                'name' => (string) ($variation['name'] ?? $variationCode),
                                'amount' => $amount,
                                'fixed_price' => $fixedPrice,
                                'is_active' => true,
                                'raw_payload' => $variation,
                            ],
                        );

                        $this->classificationService->applyClassification($providerVariation);

                        if ($existing) {
                            $summary['variations_updated']++;
                        } else {
                            $summary['variations_added']++;
                        }
                    }

                    $deactivated = ProviderVariation::query()
                        ->where('provider_service_id', $service->id)
                        ->when(
                            $syncedCodes !== [],
                            fn ($query) => $query->whereNotIn('variation_code', $syncedCodes),
                        )
                        ->update(['is_active' => false]);

                    $summary['variations_deactivated'] += $deactivated;
                });

                $summary['services_synced']++;
            } catch (VTPassException $exception) {
                $summary['failures'][] = $service->service_id.': '.$exception->getMessage();
            }
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function extractVariations(array $response): array
    {
        $variations = data_get($response, 'content.variations');

        if (! is_array($variations)) {
            return [];
        }

        return array_values(array_filter(
            $variations,
            fn (mixed $variation) => is_array($variation),
        ));
    }

    /**
     * @param  array<string, mixed>  $variation
     */
    private function parseAmount(array $variation): ?int
    {
        $raw = $variation['variation_amount'] ?? $variation['amount'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) round((float) $raw);
    }
}
