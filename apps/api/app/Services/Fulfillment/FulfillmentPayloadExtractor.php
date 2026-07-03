<?php

namespace App\Services\Fulfillment;

use App\Models\Transaction;

class FulfillmentPayloadExtractor
{
    /** @var list<string> */
    private const ELECTRICITY_FIELDS = [
        'token',
        'purchased_code',
        'units',
        'tariff',
        'resetToken',
        'configureToken',
        'tokenAmount',
        'costOfUnit',
        'tariffBaseRate',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function extractPublicDetails(Transaction $transaction): ?array
    {
        if ($transaction->product_type !== 'electricity') {
            return null;
        }

        $details = $this->extractElectricityFields(
            data_get($transaction->response_payload, 'fulfillment'),
        );

        if ($details === []) {
            return null;
        }

        return $details;
    }

    /**
     * @param  mixed  $fulfillment
     * @return array<string, mixed>
     */
    public function extractElectricityFields(mixed $fulfillment): array
    {
        if (! is_array($fulfillment)) {
            return [];
        }

        $details = [];

        foreach (self::ELECTRICITY_FIELDS as $field) {
            $value = $this->resolveField($fulfillment, $field);

            if ($value !== null && $value !== '') {
                $details[$field] = $value;
            }
        }

        return $details;
    }

    /**
     * @param  array<string, mixed>  $fulfillment
     */
    private function resolveField(array $fulfillment, string $field): mixed
    {
        $paths = [
            $field,
            'content.transactions.'.$field,
            'content.'.$field,
        ];

        foreach ($paths as $path) {
            $value = data_get($fulfillment, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
