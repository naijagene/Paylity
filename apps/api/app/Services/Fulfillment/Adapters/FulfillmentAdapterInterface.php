<?php

namespace App\Services\Fulfillment\Adapters;

use App\Models\Transaction;

interface FulfillmentAdapterInterface
{
    public function supports(string $productType): bool;

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Transaction $transaction): array;
}
