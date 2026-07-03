<?php

namespace App\Services\Fulfillment\Adapters;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;

class ElectricityAdapter implements FulfillmentAdapterInterface
{
    public const DISCO_SERVICE_IDS = [
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

        $this->assertMeterInput($disco, $meterNumber, $meterType);

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

    /**
     * @return array{
     *     serviceID: string,
     *     billersCode: string,
     *     type: string
     * }
     */
    public function buildVerifyPayload(string $disco, string $meterNumber, string $meterType): array
    {
        $normalizedDisco = strtolower(trim($disco));
        $normalizedMeterType = strtolower(trim($meterType));

        $this->assertMeterInput($normalizedDisco, trim($meterNumber), $normalizedMeterType);

        return [
            'serviceID' => self::DISCO_SERVICE_IDS[$normalizedDisco],
            'billersCode' => trim($meterNumber),
            'type' => $normalizedMeterType,
        ];
    }

    /**
     * @return list<string>
     */
    public function supportedDiscos(): array
    {
        return array_keys(self::DISCO_SERVICE_IDS);
    }

    public function resolveServiceId(string $disco): string
    {
        $normalizedDisco = strtolower(trim($disco));

        if (! isset(self::DISCO_SERVICE_IDS[$normalizedDisco])) {
            throw new FulfillmentException(
                'Unsupported electricity disco for VTPass verification.',
                'UNSUPPORTED_DISCO',
            );
        }

        return self::DISCO_SERVICE_IDS[$normalizedDisco];
    }

    private function assertMeterInput(string $disco, string $meterNumber, string $meterType): void
    {
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
    }

    private function requestId(Transaction $transaction): string
    {
        return $transaction->reference.'-'.now()->format('His');
    }
}
