<?php

namespace App\Services\Catalog;

use App\Exceptions\ProductCatalogValidationException;
use App\Models\ProductCategory;
use App\Models\ProviderService;
use App\Models\ProviderVariation;

class ProductCatalogService
{
    public const PROVIDER_VTPASS = 'vtpass';

    /** @var array<string, string> */
    private const NETWORK_ALIASES = [
        'mtn' => 'mtn',
        'airtel' => 'airtel',
        'glo' => 'glo',
        '9mobile' => '9mobile',
        'etisalat' => '9mobile',
    ];

    /** @var array<string, string> */
    private const DISCO_ALIASES = [
        'aedc' => 'aedc',
        'abuja-electric' => 'aedc',
        'ekedc' => 'ekedc',
        'eko-electric' => 'ekedc',
        'ikedc' => 'ikedc',
        'ikeja-electric' => 'ikedc',
        'phed' => 'phed',
        'portharcourt-electric' => 'phed',
        'ibedc' => 'ibedc',
        'ibadan-electric' => 'ibedc',
        'kedco' => 'kedco',
        'kano-electric' => 'kedco',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function validateAndEnrichCheckout(
        string $productType,
        int $productAmount,
        array $payload,
    ): array {
        return match ($productType) {
            'airtime' => $this->validateAirtime($productAmount, $payload),
            'data' => $this->validateData($productAmount, $payload),
            'electricity' => $this->validateElectricity($productAmount, $payload),
            default => throw new ProductCatalogValidationException(
                'Unsupported product type for catalog validation.',
                'INVALID_PRODUCT_TYPE',
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogResponse(
        ?string $category = null,
        bool $includeHidden = false,
    ): array {
        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('key')
            ->get(['key', 'name', 'is_active'])
            ->map(fn (ProductCategory $item) => [
                'key' => $item->key,
                'name' => $item->name,
                'is_active' => $item->is_active,
            ])
            ->values()
            ->all();

        $response = [
            'categories' => $categories,
            'provider' => self::PROVIDER_VTPASS,
        ];

        if ($category === null || $category === 'airtime') {
            $response['airtime_networks'] = $this->mapNetworkServices('airtime');
        }

        if ($category === null || $category === 'data') {
            $response['data_services'] = $this->mapDataServices($includeHidden);
            $response['catalog_meta'] = $this->dataCatalogMeta();
        }

        if ($category === null || $category === 'electricity') {
            $response['electricity_discos'] = $this->mapNetworkServices('electricity');
        }

        return $response;
    }

    public function findActiveService(
        string $categoryKey,
        string $serviceName,
        string $provider = self::PROVIDER_VTPASS,
    ): ?ProviderService {
        return ProviderService::query()
            ->where('provider', $provider)
            ->where('category_key', $categoryKey)
            ->where('service_name', $this->normalizeNetworkKey($serviceName))
            ->where('is_active', true)
            ->first();
    }

    public function findActiveVariation(
        ProviderService $service,
        string $variationCode,
        bool $requireVisible = true,
    ): ?ProviderVariation {
        return ProviderVariation::query()
            ->where('provider_service_id', $service->id)
            ->where('variation_code', $variationCode)
            ->where('is_active', true)
            ->when($requireVisible, fn ($query) => $query->where('is_visible', true))
            ->first();
    }

    public function normalizeNetworkKey(string $network): string
    {
        $normalized = strtolower(trim($network));

        return self::NETWORK_ALIASES[$normalized] ?? $normalized;
    }

    public function normalizeDiscoKey(string $disco): string
    {
        $normalized = strtolower(trim(str_replace(['_', ' '], '-', $disco)));

        return self::DISCO_ALIASES[$normalized] ?? $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateAirtime(int $productAmount, array $payload): array
    {
        $network = (string) ($payload['network'] ?? '');

        if ($network === '') {
            throw new ProductCatalogValidationException(
                'Airtime network is required.',
                'INVALID_PRODUCT_VARIATION',
            );
        }

        $service = $this->findActiveService('airtime', $network);

        if (! $service) {
            throw new ProductCatalogValidationException(
                'Selected airtime network is unavailable.',
                'INVALID_PRODUCT_VARIATION',
            );
        }

        return array_merge($payload, [
            'network' => strtoupper($network),
            'service_id' => $service->service_id,
            'provider' => self::PROVIDER_VTPASS,
            'catalog_validated' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateData(int $productAmount, array $payload): array
    {
        $network = (string) ($payload['network'] ?? '');
        $variationCode = (string) (
            $payload['variation_code']
            ?? $payload['data_plan_id']
            ?? ''
        );

        if ($network === '' || $variationCode === '') {
            throw new ProductCatalogValidationException(
                'Data plan selection is required.',
                'INVALID_PRODUCT_VARIATION',
            );
        }

        $service = $this->findActiveService('data', $network);

        if (! $service) {
            throw new ProductCatalogValidationException(
                'This data plan is currently unavailable. Please choose another plan.',
                'INVALID_PRODUCT_VARIATION',
            );
        }

        $variation = $this->findActiveVariation($service, $variationCode);

        if (! $variation) {
            throw new ProductCatalogValidationException(
                'This data plan is currently unavailable. Please choose another plan.',
                'INVALID_PRODUCT_VARIATION',
            );
        }

        if ($variation->fixed_price && $variation->amount !== null && $variation->amount !== $productAmount) {
            throw new ProductCatalogValidationException(
                'Selected data plan amount does not match the catalog price.',
                'INVALID_PRODUCT_VARIATION',
            );
        }

        $displayName = $variation->display_name ?: $variation->name;

        return array_merge($payload, [
            'network' => strtoupper($network),
            'variation_code' => $variation->variation_code,
            'data_plan_id' => $variation->variation_code,
            'plan_name' => $displayName,
            'provider_variation_name' => $variation->name,
            'display_name' => $variation->display_name,
            'service_id' => $service->service_id,
            'provider' => self::PROVIDER_VTPASS,
            'catalog_validated' => true,
            'catalog_is_visible' => $variation->is_visible,
            'display_override' => $variation->display_override,
            'customer_category' => $variation->customer_category,
            'data_size_label' => $variation->data_size_label,
            'validity_label' => $variation->validity_label,
            'fixed_price' => $variation->fixed_price,
            'catalog_amount' => $variation->amount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateElectricity(int $productAmount, array $payload): array
    {
        $disco = (string) ($payload['disco'] ?? '');

        if ($disco === '') {
            throw new ProductCatalogValidationException(
                'Electricity disco is required.',
                'INVALID_PRODUCT_VARIATION',
            );
        }

        $service = $this->findActiveService(
            'electricity',
            $this->normalizeDiscoKey($disco),
        );

        if (! $service) {
            throw new ProductCatalogValidationException(
                'Selected electricity provider is unavailable.',
                'INVALID_PRODUCT_VARIATION',
            );
        }

        return array_merge($payload, [
            'disco' => strtoupper($disco),
            'service_id' => $service->service_id,
            'provider' => self::PROVIDER_VTPASS,
            'catalog_validated' => true,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapNetworkServices(string $categoryKey): array
    {
        return ProviderService::query()
            ->where('provider', self::PROVIDER_VTPASS)
            ->where('category_key', $categoryKey)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get()
            ->map(fn (ProviderService $service) => [
                'service_name' => $service->service_name,
                'service_id' => $service->service_id,
                'display_name' => $service->display_name,
                'network' => strtoupper($service->service_name === '9mobile' ? '9mobile' : $service->service_name),
                'disco' => strtoupper($service->service_name),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapDataServices(bool $includeHidden = false): array
    {
        return ProviderService::query()
            ->with(['variations' => function ($query) use ($includeHidden) {
                $query->where('is_active', true)
                    ->when(! $includeHidden, fn ($builder) => $builder->where('is_visible', true))
                    ->orderByRaw('amount IS NULL')
                    ->orderBy('amount')
                    ->orderByRaw('sort_order IS NULL')
                    ->orderBy('sort_order');
            }])
            ->where('provider', self::PROVIDER_VTPASS)
            ->where('category_key', 'data')
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get()
            ->map(fn (ProviderService $service) => [
                'service_name' => $service->service_name,
                'service_id' => $service->service_id,
                'display_name' => $service->display_name,
                'network' => strtoupper($service->service_name === '9mobile' ? '9mobile' : $service->service_name),
                'variations' => $service->variations
                    ->map(fn (ProviderVariation $variation) => $this->mapVariation($variation, $includeHidden))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapVariation(ProviderVariation $variation, bool $includeHidden): array
    {
        $mapped = [
            'variation_code' => $variation->variation_code,
            'name' => $variation->name,
            'display_name' => $variation->display_name ?: $variation->name,
            'amount' => $variation->amount,
            'fixed_price' => $variation->fixed_price,
            'is_popular' => $variation->is_popular,
            'validity_label' => $variation->validity_label,
            'data_size_label' => $variation->data_size_label,
            'customer_category' => $variation->customer_category,
            'sort_order' => $variation->sort_order,
        ];

        if ($includeHidden) {
            $mapped['is_visible'] = $variation->is_visible;
            $mapped['display_override'] = $variation->display_override;
        }

        return $mapped;
    }

    /**
     * @return array{
     *     total_variations: int,
     *     visible_variations: int,
     *     hidden_variations: int
     * }
     */
    private function dataCatalogMeta(): array
    {
        $serviceIds = ProviderService::query()
            ->where('provider', self::PROVIDER_VTPASS)
            ->where('category_key', 'data')
            ->where('is_active', true)
            ->pluck('id');

        $total = ProviderVariation::query()
            ->whereIn('provider_service_id', $serviceIds)
            ->where('is_active', true)
            ->count();

        $visible = ProviderVariation::query()
            ->whereIn('provider_service_id', $serviceIds)
            ->where('is_active', true)
            ->where('is_visible', true)
            ->count();

        return [
            'total_variations' => $total,
            'visible_variations' => $visible,
            'hidden_variations' => max(0, $total - $visible),
        ];
    }
}
