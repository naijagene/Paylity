<?php

namespace App\Services\Catalog;

use App\Models\ProviderVariation;
use InvalidArgumentException;

final class VariationClassificationResult
{
    public const KEY_DISPLAY_NAME = 'display_name';

    public const KEY_IS_VISIBLE = 'is_visible';

    public const KEY_CUSTOMER_CATEGORY = 'customer_category';

    public const KEY_VALIDITY_LABEL = 'validity_label';

    public const KEY_DATA_SIZE_LABEL = 'data_size_label';

    public const KEY_SORT_ORDER = 'sort_order';

    /** @var list<string> */
    public const PERSISTENCE_KEYS = [
        self::KEY_DISPLAY_NAME,
        self::KEY_IS_VISIBLE,
        self::KEY_CUSTOMER_CATEGORY,
        self::KEY_VALIDITY_LABEL,
        self::KEY_DATA_SIZE_LABEL,
        self::KEY_SORT_ORDER,
    ];

    private function __construct(
        public readonly ?string $displayName,
        private readonly bool $visible,
        public readonly string $customerCategory,
        public readonly ?string $validityLabel,
        public readonly ?string $dataSizeLabel,
        public readonly ?int $sortOrder,
    ) {
    }

    /**
     * @param  array<string, mixed>  $classified
     */
    public static function fromClassifierArray(array $classified): self
    {
        foreach (self::PERSISTENCE_KEYS as $key) {
            if (! array_key_exists($key, $classified)) {
                throw new InvalidArgumentException("Classifier result missing required key [{$key}].");
            }
        }

        return new self(
            displayName: is_null($classified[self::KEY_DISPLAY_NAME])
                ? null
                : (string) $classified[self::KEY_DISPLAY_NAME],
            visible: (bool) $classified[self::KEY_IS_VISIBLE],
            customerCategory: (string) $classified[self::KEY_CUSTOMER_CATEGORY],
            validityLabel: is_null($classified[self::KEY_VALIDITY_LABEL])
                ? null
                : (string) $classified[self::KEY_VALIDITY_LABEL],
            dataSizeLabel: is_null($classified[self::KEY_DATA_SIZE_LABEL])
                ? null
                : (string) $classified[self::KEY_DATA_SIZE_LABEL],
            sortOrder: is_null($classified[self::KEY_SORT_ORDER])
                ? null
                : (int) $classified[self::KEY_SORT_ORDER],
        );
    }

    public static function fromModel(ProviderVariation $variation): self
    {
        return new self(
            displayName: $variation->display_name,
            visible: (bool) $variation->is_visible,
            customerCategory: (string) ($variation->customer_category ?? 'unknown'),
            validityLabel: $variation->validity_label,
            dataSizeLabel: $variation->data_size_label,
            sortOrder: $variation->sort_order,
        );
    }

    /**
     * @return array{
     *     display_name: string|null,
     *     is_visible: bool,
     *     customer_category: string,
     *     validity_label: string|null,
     *     data_size_label: string|null,
     *     sort_order: int|null
     * }
     */
    public function toPersistenceArray(): array
    {
        return [
            self::KEY_DISPLAY_NAME => $this->displayName,
            self::KEY_IS_VISIBLE => $this->visible,
            self::KEY_CUSTOMER_CATEGORY => $this->customerCategory,
            self::KEY_VALIDITY_LABEL => $this->validityLabel,
            self::KEY_DATA_SIZE_LABEL => $this->dataSizeLabel,
            self::KEY_SORT_ORDER => $this->sortOrder,
        ];
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function isHidden(): bool
    {
        return ! $this->visible;
    }
}
