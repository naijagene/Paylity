<?php

namespace App\Services\Fulfillment\Adapters;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;
use App\Services\Fulfillment\VTPassRequestIdGenerator;

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

    /** @var array<string, string> */
    private const DISCO_ALIASES = [
        'aedc' => 'aedc',
        'abuja-electric' => 'aedc',
        'ekedc' => 'ekedc',
        'eko-electric' => 'ekedc',
        'ikedc' => 'ikedc',
        'ikeja-electric' => 'ikedc',
        'phed' => 'phed',
        'ibedc' => 'ibedc',
        'ibadan-electric' => 'ibedc',
        'kedco' => 'kedco',
        'kano-electric' => 'kedco',
    ];

    public function supports(string $productType): bool
    {
        return $productType === 'electricity';
    }

    public function buildPayload(Transaction $transaction): array
    {
        $payload = (array) $transaction->request_payload;
        $discoKey = $this->resolveDiscoKey((string) ($payload['disco'] ?? ''));
        $meterNumber = (string) ($payload['meter_number'] ?? '');
        $meterType = strtolower((string) ($payload['meter_type'] ?? ''));

        $this->assertMeterInput($discoKey, $meterNumber, $meterType);

        return [
            'request_id' => VTPassRequestIdGenerator::forTransaction($transaction),
            'serviceID' => self::DISCO_SERVICE_IDS[$discoKey],
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
        $discoKey = $this->resolveDiscoKey($disco);
        $normalizedMeterType = strtolower(trim($meterType));

        $this->assertMeterInput($discoKey, trim($meterNumber), $normalizedMeterType);

        return [
            'serviceID' => self::DISCO_SERVICE_IDS[$discoKey],
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

    public function normalizeDisco(string $disco): string
    {
        $normalized = strtolower(trim(str_replace(['_', ' '], '-', $disco)));

        return self::DISCO_ALIASES[$normalized] ?? $normalized;
    }

    public function resolveServiceId(string $disco): string
    {
        $discoKey = $this->resolveDiscoKey($disco);

        return self::DISCO_SERVICE_IDS[$discoKey];
    }

    private function resolveDiscoKey(string $disco): string
    {
        $discoKey = $this->normalizeDisco($disco);

        if (! isset(self::DISCO_SERVICE_IDS[$discoKey])) {
            throw new FulfillmentException(
                'Unsupported electricity disco for VTPass fulfillment.',
                'UNSUPPORTED_DISCO',
            );
        }

        return $discoKey;
    }

    private function assertMeterInput(string $discoKey, string $meterNumber, string $meterType): void
    {
        if (! isset(self::DISCO_SERVICE_IDS[$discoKey])) {
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
}
