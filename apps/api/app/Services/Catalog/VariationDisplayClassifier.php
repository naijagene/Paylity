<?php

namespace App\Services\Catalog;

class VariationDisplayClassifier
{
    private const ENTERPRISE_AMOUNT_THRESHOLD = 50000;

    /** @var list<string> */
    private const VOICE_SUBSTRINGS = [
        'xtratalk',
        'xtradata',
        'xtra talk',
        'xtra data',
    ];

    /** @var list<string> */
    private const VOICE_WORDS = [
        'voice',
        'talk',
        'call',
        'minute',
        'minutes',
    ];

    /** @var list<string> */
    private const SME_SUBSTRINGS = [
        'corporate gifting',
        'sme data',
    ];

    /** @var list<string> */
    private const SME_WORDS = [
        'sme',
        'corporate',
        'gifting',
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
        'enterprise' => 930,
    ];

    public function classify(
        string $name,
        ?int $amount,
        string $variationCode,
        string $extraSearchText = '',
    ): VariationClassificationResult {
        $providerName = trim($name);
        $searchCorpus = $this->buildSearchCorpus($providerName, $variationCode, $extraSearchText);
        $labelForExtraction = $providerName !== '' ? $providerName : $variationCode;

        if (trim($variationCode) === '') {
            return $this->hiddenResult($labelForExtraction, 'unknown', null, null, $amount);
        }

        if ($this->matchesVoiceBundle($searchCorpus)) {
            return $this->hiddenResult(
                $labelForExtraction,
                'voice',
                $this->extractDataSizeLabel($labelForExtraction),
                $this->extractValidityLabel($labelForExtraction),
                $amount,
            );
        }

        if ($this->matchesSmeCorporate($searchCorpus)) {
            $category = $this->containsWord($searchCorpus, 'corporate') ? 'corporate' : 'sme';

            return $this->hiddenResult(
                $labelForExtraction,
                $category,
                $this->extractDataSizeLabel($labelForExtraction),
                $this->extractValidityLabel($labelForExtraction),
                $amount,
            );
        }

        if ($this->matchesEnterpriseAmount($amount, $providerName, $variationCode, $extraSearchText)) {
            return $this->hiddenResult(
                $labelForExtraction,
                'enterprise',
                $this->extractDataSizeLabel($labelForExtraction),
                $this->extractValidityLabel($labelForExtraction),
                $amount,
            );
        }

        $dataSizeLabel = $this->extractDataSizeLabel($labelForExtraction);
        $validityLabel = $this->extractValidityLabel($labelForExtraction);
        $customerCategory = $this->detectCustomerCategory($searchCorpus);
        $displayName = $this->buildDisplayName($dataSizeLabel, $validityLabel, $labelForExtraction);

        return VariationClassificationResult::fromClassifierArray([
            'display_name' => $displayName,
            'is_visible' => true,
            'customer_category' => $customerCategory,
            'validity_label' => $validityLabel,
            'data_size_label' => $dataSizeLabel,
            'sort_order' => $this->computeSortOrder($customerCategory, $amount),
        ]);
    }

    private function hiddenResult(
        string $providerName,
        string $category,
        ?string $dataSizeLabel,
        ?string $validityLabel,
        ?int $amount,
    ): VariationClassificationResult {
        return VariationClassificationResult::fromClassifierArray([
            'display_name' => $this->buildDisplayName($dataSizeLabel, $validityLabel, $providerName),
            'is_visible' => false,
            'customer_category' => $category,
            'validity_label' => $validityLabel,
            'data_size_label' => $dataSizeLabel,
            'sort_order' => $this->computeSortOrder($category, $amount),
        ]);
    }

    private function buildSearchCorpus(
        string $name,
        string $variationCode,
        string $extraSearchText,
    ): string {
        return $this->normalizeSearchText(implode(' ', array_filter([
            $name,
            $variationCode,
            $extraSearchText,
        ], fn (string $value): bool => trim($value) !== '')));
    }

    private function normalizeSearchText(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['_', '-', '/', '\\'], ' ', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function matchesVoiceBundle(string $searchCorpus): bool
    {
        $compact = str_replace(' ', '', $searchCorpus);

        foreach (self::VOICE_SUBSTRINGS as $substring) {
            $normalizedSubstring = str_replace(' ', '', $substring);

            if (str_contains($searchCorpus, $substring)
                || str_contains($compact, $normalizedSubstring)) {
                return true;
            }
        }

        foreach (self::VOICE_WORDS as $word) {
            if ($this->containsWord($searchCorpus, $word)) {
                return true;
            }
        }

        return false;
    }

    private function matchesSmeCorporate(string $searchCorpus): bool
    {
        foreach (self::SME_SUBSTRINGS as $substring) {
            if (str_contains($searchCorpus, $substring)) {
                return true;
            }
        }

        foreach (self::SME_WORDS as $word) {
            if ($this->containsWord($searchCorpus, $word)) {
                return true;
            }
        }

        return false;
    }

    private function matchesEnterpriseAmount(
        ?int $amount,
        string $name,
        string $variationCode,
        string $extraSearchText,
    ): bool {
        if ($amount !== null && $amount > self::ENTERPRISE_AMOUNT_THRESHOLD) {
            return true;
        }

        $rawText = implode(' ', array_filter([$name, $variationCode, $extraSearchText]));

        return $this->extractMaxAmountFromText($rawText) > self::ENTERPRISE_AMOUNT_THRESHOLD;
    }

    private function extractMaxAmountFromText(string $text): int
    {
        $max = 0;

        if (preg_match_all('/(?:n\s?)?([\d]{1,3}(?:,\d{3})+|\d+)/i', $text, $matches)) {
            foreach ($matches[1] as $raw) {
                $value = (int) str_replace(',', '', $raw);

                if ($value > $max) {
                    $max = $value;
                }
            }
        }

        return $max;
    }

    private function containsWord(string $searchCorpus, string $word): bool
    {
        return (bool) preg_match('/\b'.preg_quote($word, '/').'\b/u', $searchCorpus);
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

    private function detectCustomerCategory(string $searchCorpus): string
    {
        if ($this->containsAny($searchCorpus, ['365 days', '365 day', 'yearly', '1 year', '12 months', '12 month'])) {
            return 'yearly';
        }

        if ($this->containsAny($searchCorpus, ['30 days', '30 day', 'monthly', '1 month'])) {
            return 'monthly';
        }

        if ($this->containsAny($searchCorpus, ['7 days', '7 day', 'weekly', '1 week'])) {
            return 'weekly';
        }

        if ($this->containsAny($searchCorpus, ['1 day', '24 hrs', '24 hours', 'daily'])) {
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
