<?php

namespace App\Services\Fulfillment\Adapters;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;
use App\Services\Catalog\ProductCatalogService;
use App\Services\Fulfillment\ElectricityDiscoMapper;
use App\Services\Fulfillment\VTPassRequestIdGenerator;

class ElectricityAdapter implements FulfillmentAdapterInterface
{
    public function __construct(
        private readonly ElectricityDiscoMapper $discoMapper,
        private readonly ProductCatalogService $productCatalogService,
    ) {
    }

    public function supports(string $productType): bool
    {
        return $productType === 'electricity';
    }

    public function buildPayload(Transaction $transaction): array
    {
        $payload = (array) $transaction->request_payload;
        $discoKey = $this->discoMapper->resolveDiscoKey((string) ($payload['disco'] ?? ''));
        $meterNumber = (string) ($payload['meter_number'] ?? '');
        $meterType = strtolower((string) ($payload['meter_type'] ?? ''));

        $this->assertMeterInput($discoKey, $meterNumber, $meterType);

        $serviceId = (string) ($payload['service_id'] ?? '');

        if ($serviceId === '') {
            $service = $this->productCatalogService->findActiveService('electricity', $discoKey);
            $serviceId = $service?->service_id ?? $this->discoMapper->resolveServiceId($discoKey);
        }

        return [
            'request_id' => VTPassRequestIdGenerator::forTransaction($transaction),
            'serviceID' => $serviceId,
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
        $discoKey = $this->discoMapper->resolveDiscoKey($disco);
        $normalizedMeterType = strtolower(trim($meterType));

        $this->assertMeterInput($discoKey, trim($meterNumber), $normalizedMeterType);

        return [
            'serviceID' => $this->discoMapper->resolveServiceId($discoKey),
            'billersCode' => trim($meterNumber),
            'type' => $normalizedMeterType,
        ];
    }

    /**
     * @return list<string>
     */
    public function supportedDiscos(): array
    {
        return $this->discoMapper->supportedDiscos();
    }

    public function normalizeDisco(string $disco): string
    {
        return $this->discoMapper->normalizeDisco($disco);
    }

    public function resolveServiceId(string $disco): string
    {
        return $this->discoMapper->resolveServiceId($disco);
    }

    private function assertMeterInput(string $discoKey, string $meterNumber, string $meterType): void
    {
        if (! in_array($discoKey, $this->discoMapper->supportedDiscos(), true)) {
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
