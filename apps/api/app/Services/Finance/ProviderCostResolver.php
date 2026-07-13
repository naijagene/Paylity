<?php

namespace App\Services\Finance;

use App\Enums\ProviderCostStatus;
use App\Models\FulfillmentAttempt;
use App\Models\ProviderVariation;
use App\Models\Transaction;

class ProviderCostResolver
{
    /**
     * @return array{
     *     provider_cost_naira: int,
     *     provider_cost_source: string,
     *     provider_cost_status: string
     * }
     */
    public function resolve(Transaction $transaction, ?FulfillmentAttempt $attempt = null): array
    {
        $actual = $this->resolveFromProviderResponse($attempt);

        if ($actual !== null) {
            return [
                'provider_cost_naira' => $actual,
                'provider_cost_source' => 'provider_response',
                'provider_cost_status' => ProviderCostStatus::ACTUAL,
            ];
        }

        $catalog = $this->resolveFromCatalog($transaction);

        if ($catalog !== null) {
            return [
                'provider_cost_naira' => $catalog,
                'provider_cost_source' => 'catalog_variation',
                'provider_cost_status' => ProviderCostStatus::CATALOG,
            ];
        }

        $configured = $this->resolveConfigured($transaction);

        if ($configured !== null) {
            return [
                'provider_cost_naira' => $configured,
                'provider_cost_source' => 'configured',
                'provider_cost_status' => ProviderCostStatus::CONFIGURED,
            ];
        }

        return [
            'provider_cost_naira' => (int) $transaction->product_amount,
            'provider_cost_source' => 'product_amount',
            'provider_cost_status' => ProviderCostStatus::PROVISIONAL,
        ];
    }

    private function resolveFromProviderResponse(?FulfillmentAttempt $attempt): ?int
    {
        if (! $attempt) {
            return null;
        }

        $response = (array) ($attempt->response_payload ?? []);

        foreach (['amount', 'cost', 'unit_cost', 'transaction_amount'] as $field) {
            $value = data_get($response, "content.transactions.{$field}")
                ?? data_get($response, "content.{$field}")
                ?? data_get($response, $field);

            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    private function resolveFromCatalog(Transaction $transaction): ?int
    {
        $variationCode = data_get($transaction->request_payload, 'variation_code');

        if (! is_string($variationCode) || $variationCode === '') {
            return null;
        }

        $variation = ProviderVariation::query()
            ->where('variation_code', $variationCode)
            ->first();

        if (! $variation || (int) $variation->amount <= 0) {
            return null;
        }

        return (int) $variation->amount;
    }

    private function resolveConfigured(Transaction $transaction): ?int
    {
        $configured = config('finance.product_costs.'.$transaction->product_type);

        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        return null;
    }

    public function grossMarginKobo(
        int $productAmountKobo,
        int $providerCostKobo,
        int $convenienceFeeKobo,
        int $gatewayFeeRecoveryKobo,
        int $gatewayFeeExpenseKobo,
    ): int {
        return $productAmountKobo
            - $providerCostKobo
            + $convenienceFeeKobo
            + $gatewayFeeRecoveryKobo
            - $gatewayFeeExpenseKobo;
    }
}
