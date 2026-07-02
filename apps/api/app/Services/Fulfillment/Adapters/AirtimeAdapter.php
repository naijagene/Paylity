<?php

namespace App\Services\Fulfillment\Adapters;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;

class AirtimeAdapter implements FulfillmentAdapterInterface
{
    private const NETWORK_SERVICE_IDS = [
        'mtn' => 'mtn',
        'airtel' => 'airtel',
        'glo' => 'glo',
        '9mobile' => 'etisalat',
        'etisalat' => 'etisalat',
    ];

    public function supports(string $productType): bool
    {
        return $productType === 'airtime';
    }

    public function buildPayload(Transaction $transaction): array
    {
        $payload = (array) $transaction->request_payload;
        $network = strtolower((string) ($payload['network'] ?? ''));

        if (! isset(self::NETWORK_SERVICE_IDS[$network])) {
            throw new FulfillmentException(
                'Unsupported airtime network for VTPass fulfillment.',
                'UNSUPPORTED_NETWORK',
            );
        }

        $phone = (string) ($payload['recipient_phone'] ?? $transaction->customer_phone);

        return [
            'request_id' => $this->requestId($transaction),
            'serviceID' => self::NETWORK_SERVICE_IDS[$network],
            'amount' => $transaction->product_amount,
            'phone' => $phone,
        ];
    }

    private function requestId(Transaction $transaction): string
    {
        return $transaction->reference.'-'.now()->format('His');
    }
}
