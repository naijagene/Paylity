<?php

namespace App\Services\Fulfillment\Adapters;

use App\Exceptions\FulfillmentException;
use App\Models\Transaction;
use App\Services\Catalog\ProductCatalogService;
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

    /** @var list<string> */
    public const REQUIRED_VTPASS_FIELDS = [
        'request_id',
        'serviceID',
        'billersCode',
        'variation_code',
        'phone',
    ];

    public function __construct(
        private readonly ProductCatalogService $productCatalogService,
    ) {
    }

    public function supports(string $productType): bool
    {
        return $productType === 'data';
    }

    public function buildPayload(Transaction $transaction): array
    {
        $payload = (array) $transaction->request_payload;
        $network = strtolower((string) ($payload['network'] ?? ''));

        $serviceId = (string) ($payload['service_id'] ?? '');

        if ($serviceId === '') {
            $service = $this->productCatalogService->findActiveService('data', $network);
            $serviceId = $service?->service_id
                ?? self::NETWORK_SERVICE_IDS[$this->productCatalogService->normalizeNetworkKey($network)] ?? '';
        }

        if ($serviceId === '') {
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

        $recipientPhone = trim((string) (
            $payload['recipient_phone']
            ?? $payload['phone']
            ?? $transaction->customer_phone
        ));

        if ($recipientPhone === '') {
            throw new FulfillmentException(
                'Recipient phone number is required for VTPass data fulfillment.',
                'MISSING_RECIPIENT_PHONE',
            );
        }

        $billersCode = trim((string) (
            $payload['billers_code']
            ?? $payload['billersCode']
            ?? $recipientPhone
        ));

        $contactPhone = trim((string) ($payload['contact_phone'] ?? $recipientPhone));
        $amount = $transaction->product_amount;

        if (! empty($payload['fixed_price']) && isset($payload['catalog_amount'])) {
            $amount = (int) $payload['catalog_amount'];
        }

        return [
            'request_id' => VTPassRequestIdGenerator::forTransaction($transaction),
            'serviceID' => $serviceId,
            'billersCode' => $billersCode,
            'variation_code' => $variationCode,
            'amount' => $amount,
            'phone' => $contactPhone,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitizeForDiagnostics(array $payload): array
    {
        $fields = array_merge(self::REQUIRED_VTPASS_FIELDS, ['amount']);

        return collect($fields)
            ->filter(fn (string $field) => array_key_exists($field, $payload))
            ->mapWithKeys(fn (string $field) => [$field => $payload[$field]])
            ->all();
    }
}
