<?php

namespace App\Services\Fulfillment\Adapters;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;

class ElectricityAdapter implements FulfillmentAdapterInterface
{
    private const DISCO_SERVICE_IDS = [
        'aedc' => 'abuja-electric',
        'ekedc' => 'ekedc',
        'ikedc' => 'ikeja-electric',
        'phed' => 'phed',
        'ibedc' => 'ibedc',
        'kedco' => 'kedco',
    ];

    public function supports(string $productType): bool
    {
        return $productType === 'electricity';
    }

    public function buildPayload(Transaction $transaction): array
    {
        $payload = (array) $transaction->request_payload;
        $disco = strtolower((string) ($payload['disco'] ?? ''));
        $meterNumber = (string) ($payload['meter_number'] ?? '');
        $meterType = strtolower((string) ($payload['meter_type'] ?? ''));

        if (! isset(self::DISCO_SERVICE_IDS[$disco])) {
            throw new FulfillmentException(
                'Unsupported electricity disco for VTPass fulfillment.',
                'UNSUPPORTED_DISCO',
            );
        }

        if ($meterNumber === '') {
            throw new FulfillmentException(
                'Meter number is required for electricity fulfillment.',
                'MISSING_METER_NUMBER',
            );
        }

        if (! in_array($meterType, ['prepaid', 'postpaid'], true)) {
            throw new FulfillmentException(
                'Meter type must be prepaid or postpaid.',
                'INVALID_METER_TYPE',
            );
        }

        return [
            'request_id' => $this->requestId($transaction),
            'serviceID' => self::DISCO_SERVICE_IDS[$disco],
            'billersCode' => $meterNumber,
            'variation_code' => $meterType,
            'amount' => $transaction->product_amount,
            'phone' => $transaction->customer_phone,
            'meter_number' => $meterNumber,
            'meter_type' => $meterType,
        ];
    }

    private function requestId(Transaction $transaction): string
    {
        return $transaction->reference.'-'.now()->format('His');
    }
}
