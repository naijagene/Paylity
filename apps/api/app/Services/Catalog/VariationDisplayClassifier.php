<?php

namespace App\Services\Catalog;

class VariationDisplayClassifier
{
    /** @var list<string> */
    private const VOICE_KEYWORDS = [
        'voice',
        'talk',
        'xtratalk',
        'xtradata',
        'call',
        'minutes',
    ];

    /** @var list<string> */
    private const SME_KEYWORDS = [
        'sme',
        'corporate',
        'sme data',
        'corporate gifting',
    ];

    /** @var array<string, int> */
    private const CATEGORY_SORT_BASE = [
        'daily' => 100,
        'weekly' => 200,
        'monthly' => 300,
        'yearly' => 400,
        'unknown' => 500,
        'voice' => 900,
        'sme' => 910,
        'corporate' => 920,
    ];

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
    public function classify(
        string $name,
        ?int $amount,
        string $variationCode,
    ): array {
        $normalizedName = trim($name);
        $searchName = strtolower($normalizedName);

        if ($normalizedName === '' || trim($variationCode) === '') {
            return $this->hiddenResult($normalizedName, 'unknown', null, null, $amount);
        }

        if ($this->containsAny($searchName, self::VOICE_KEYWORDS)) {
            return $this->hiddenResult(
                $normalizedName,
                'voice',
                $this->extractDataSizeLabel($normalizedName),
                $this->extractValidityLabel($normalizedName),
                $amount,
            );
        }

        if ($this->containsAny($searchName, self::SME_KEYWORDS)) {
            $category = str_contains($searchName, 'corporate') ? 'corporate' : 'sme';

            return $this->hiddenResult(
                $normalizedName,
                $category,
                $this->extractDataSizeLabel($normalizedName),
                $this->extractValidityLabel($normalizedName),
                $amount,
            );
        }

        if ($amount !== null && $amount > 50000) {
            return $this->hiddenResult(
                $normalizedName,
                'unknown',
                $this->extractDataSizeLabel($normalizedName),
                $this->extractValidityLabel($normalizedName),
                $amount,
            );
        }

        $dataSizeLabel = $this->extractDataSizeLabel($normalizedName);
        $validityLabel = $this->extractValidityLabel($normalizedName);
        $customerCategory = $this->detectCustomerCategory($searchName);
        $displayName = $this->buildDisplayName($dataSizeLabel, $validityLabel, $normalizedName);

        return [
            'display_name' => $displayName,
            'is_visible' => true,
            'customer_category' => $customerCategory,
            'validity_label' => $validityLabel,
            'data_size_label' => $dataSizeLabel,
            'sort_order' => $this->computeSortOrder($customerCategory, $amount),
        ];
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
    private function hiddenResult(
        string $providerName,
        string $category,
        ?string $dataSizeLabel,
        ?string $validityLabel,
        ?int $amount,
    ): array {
        return [
            'display_name' => $this->buildDisplayName($dataSizeLabel, $validityLabel, $providerName),
            'is_visible' => false,
            'customer_category' => $category,
            'validity_label' => $validityLabel,
            'data_size_label' => $dataSizeLabel,
            'sort_order' => $this->computeSortOrder($category, $amount),
        ];
    }

    private function buildDisplayName(
        ?string $dataSizeLabel,
        ?string $validityLabel,
        string $fallback,
    ): ?string {
        if ($dataSizeLabel && $validityLabel) {
            return $dataSizeLabel.' - '.$validityLabel;
        }

        return $fallback !== '' ? $fallback : null;
    }

    private function detectCustomerCategory(string $searchName): string
    {
        if ($this->containsAny($searchName, ['365 days', '365 day', 'yearly', '1 year', '12 months', '12 month'])) {
            return 'yearly';
        }

        if ($this->containsAny($searchName, ['30 days', '30 day', 'monthly', '1 month'])) {
            return 'monthly';
        }

        if ($this->containsAny($searchName, ['7 days', '7 day', 'weekly', '1 week'])) {
            return 'weekly';
        }

        if ($this->containsAny($searchName, ['1 day', '24 hrs', '24 hours', 'daily'])) {
            return 'daily';
        }

        return 'unknown';
    }

    private function extractDataSizeLabel(string $name): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*(MB|GB|TB)\b/i', $name, $matches)) {
            return strtoupper($matches[1].$matches[2]);
        }

        return null;
    }

    private function extractValidityLabel(string $name): ?string
    {
        if (preg_match('/\b365\s*days?\b/i', $name)) {
            return '365 Days';
        }

        if (preg_match('/\b30\s*days?\b/i', $name)) {
            return '30 Days';
        }

        if (preg_match('/\b7\s*days?\b/i', $name)) {
            return '7 Days';
        }

        if (preg_match('/\b1\s*day\b/i', $name)) {
            return '1 Day';
        }

        if (preg_match('/\b24\s*hrs?\b|\b24\s*hours?\b/i', $name)) {
            return '1 Day';
        }

        if (preg_match('/\b(\d+)\s*months?\b/i', $name, $matches)) {
            $months = (int) $matches[1];

            if ($months === 1) {
                return '30 Days';
            }

            if ($months === 12) {
                return '365 Days';
            }

            return $months.' Months';
        }

        if (preg_match('/\b(\d+)\s*weeks?\b/i', $name, $matches)) {
            $weeks = (int) $matches[1];

            return ($weeks * 7).' Days';
        }

        return null;
    }

    private function computeSortOrder(string $category, ?int $amount): ?int
    {
        $base = self::CATEGORY_SORT_BASE[$category] ?? 500;

        return ($base * 1_000_000) + max(0, $amount ?? 0);
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
