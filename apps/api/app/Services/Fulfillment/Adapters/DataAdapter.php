<?php

namespace App\Services\Fulfillment\Adapters;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;
use App\Services\Fulfillment\VTPassRequestIdGenerator;

class DataAdapter implements FulfillmentAdapterInterface
{
    private const NETWORK_SERVICE_IDS = [
        'mtn' => 'mtn-data',
        'airtel' => 'airtel-data',
        'glo' => 'glo-data',
        '9mobile' => 'etisalat-data',
        'etisalat' => 'etisalat-data',
    ];

    public function supports(string $productType): bool
    {
        return $productType === 'data';
    }

    public function buildPayload(Transaction $transaction): array
    {
        $payload = (array) $transaction->request_payload;
        $network = strtolower((string) ($payload['network'] ?? ''));

        if (! isset(self::NETWORK_SERVICE_IDS[$network])) {
            throw new FulfillmentException(
                'Unsupported data network for VTPass fulfillment.',
                'UNSUPPORTED_NETWORK',
            );
        }

        $variationCode = (string) (
            $payload['variation_code']
            ?? $payload['data_plan_id']
            ?? ''
        );

        if ($variationCode === '') {
            throw new FulfillmentException(
                'Data variation code is required for VTPass fulfillment.',
                'MISSING_VARIATION_CODE',
            );
        }

        $phone = (string) ($payload['recipient_phone'] ?? $transaction->customer_phone);

        return [
            'request_id' => VTPassRequestIdGenerator::forTransaction($transaction),
            'serviceID' => self::NETWORK_SERVICE_IDS[$network],
            'billersCode' => $phone,
            'variation_code' => $variationCode,
            'amount' => $transaction->product_amount,
            'phone' => $phone,
        ];
    }
}
