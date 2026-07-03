<?php

namespace App\Services\Fulfillment\Adapters;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;
use App\Services\Catalog\ProductCatalogService;
use App\Services\Fulfillment\VTPassRequestIdGenerator;

class AirtimeAdapter implements FulfillmentAdapterInterface
{
    private const NETWORK_SERVICE_IDS = [
        'mtn' => 'mtn',
        'airtel' => 'airtel',
        'glo' => 'glo',
        '9mobile' => 'etisalat',
        'etisalat' => 'etisalat',
    ];

    public function __construct(
        private readonly ProductCatalogService $productCatalogService,
    ) {
    }

    public function supports(string $productType): bool
    {
        return $productType === 'airtime';
    }

    public function buildPayload(Transaction $transaction): array
    {
        $payload = (array) $transaction->request_payload;
        $network = strtolower((string) ($payload['network'] ?? ''));

        $serviceId = (string) ($payload['service_id'] ?? '');

        if ($serviceId === '') {
            $service = $this->productCatalogService->findActiveService('airtime', $network);
            $serviceId = $service?->service_id
                ?? self::NETWORK_SERVICE_IDS[$this->productCatalogService->normalizeNetworkKey($network)] ?? '';
        }

        if ($serviceId === '') {
            throw new FulfillmentException(
                'Unsupported airtime network for VTPass fulfillment.',
                'UNSUPPORTED_NETWORK',
            );
        }

        $phone = (string) ($payload['recipient_phone'] ?? $transaction->customer_phone);

        return [
            'request_id' => VTPassRequestIdGenerator::forTransaction($transaction),
            'serviceID' => $serviceId,
            'amount' => $transaction->product_amount,
            'phone' => $phone,
        ];
    }
}
