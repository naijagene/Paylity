<?php

namespace App\Services\Fulfillment;

use App\Exceptions\FulfillmentException;
use App\Exceptions\VTPassException;
use App\Services\Fulfillment\Adapters\ElectricityAdapter;

class ElectricityMeterVerificationService
{
    public function __construct(
        private readonly VTPassService $vtpassService,
        private readonly ElectricityAdapter $electricityAdapter,
        private readonly VTPassResponseMapper $responseMapper,
    ) {
    }

    /**
     * @return array{
     *     verified: bool,
     *     available: bool,
     *     customer_name: string|null,
     *     meter_number: string,
     *     disco: string,
     *     status: string,
     *     message: string,
     *     minimum_amount: string|null,
     *     raw_code: string|null,
     *     diagnostics: array<string, mixed>
     * }
     */
    public function verify(string $disco, string $meterNumber, string $meterType): array
    {
        $meterNumber = trim($meterNumber);
        $disco = strtoupper(trim($disco));
        $meterType = strtolower(trim($meterType));

        if (! $this->vtpassService->isEnabled()) {
            return $this->unavailableResponse(
                $disco,
                $meterNumber,
                'VTPass meter verification is unavailable because FEATURE_VTPASS=false.',
            );
        }

        if (! $this->vtpassService->hasCredentials()) {
            return $this->unavailableResponse(
                $disco,
                $meterNumber,
                'VTPass meter verification is unavailable because sandbox credentials are not configured.',
            );
        }

        try {
            $payload = $this->electricityAdapter->buildVerifyPayload($disco, $meterNumber, $meterType);
            $response = $this->vtpassService->verifyMeter($payload);
            $mapped = $this->responseMapper->map($response);
            $diagnostics = $this->vtpassService->lastRequestDiagnostics() ?? [
                'endpoint' => 'merchant-verify',
            ];

            return [
                'verified' => $mapped['status'] === VTPassResponseMapper::STATUS_SUCCESS,
                'available' => true,
                'customer_name' => $this->resolveCustomerName($response),
                'meter_number' => (string) (
                    data_get($response, 'content.Meter_Number')
                    ?? data_get($response, 'content.meter_number')
                    ?? $meterNumber
                ),
                'disco' => $disco,
                'status' => $mapped['status'],
                'message' => $mapped['message'],
                'minimum_amount' => data_get($response, 'content.Minimum_Amount')
                    ?? data_get($response, 'content.Minimium_Purchase_Amount'),
                'raw_code' => $mapped['code'],
                'diagnostics' => $diagnostics,
            ];
        } catch (FulfillmentException $exception) {
            return [
                'verified' => false,
                'available' => true,
                'customer_name' => null,
                'meter_number' => $meterNumber,
                'disco' => $disco,
                'status' => VTPassResponseMapper::STATUS_FAILED,
                'message' => $exception->getMessage(),
                'minimum_amount' => null,
                'raw_code' => $exception->errorCode,
                'diagnostics' => [
                    'endpoint' => 'merchant-verify',
                    'vtpass_message' => $exception->getMessage(),
                ],
            ];
        } catch (VTPassException $exception) {
            return [
                'verified' => false,
                'available' => true,
                'customer_name' => null,
                'meter_number' => $meterNumber,
                'disco' => $disco,
                'status' => VTPassResponseMapper::STATUS_FAILED,
                'message' => $exception->getMessage(),
                'minimum_amount' => null,
                'raw_code' => $exception->errorCode,
                'diagnostics' => array_merge(
                    ['endpoint' => 'merchant-verify'],
                    $exception->safeContext(),
                ),
            ];
        }
    }

    /**
     * @return array{
     *     verified: bool,
     *     available: bool,
     *     customer_name: string|null,
     *     meter_number: string,
     *     disco: string,
     *     status: string,
     *     message: string,
     *     minimum_amount: string|null,
     *     raw_code: string|null,
     *     diagnostics: array<string, mixed>
     * }
     */
    private function unavailableResponse(string $disco, string $meterNumber, string $message): array
    {
        return [
            'verified' => false,
            'available' => false,
            'customer_name' => null,
            'meter_number' => $meterNumber,
            'disco' => $disco,
            'status' => 'unavailable',
            'message' => $message,
            'minimum_amount' => null,
            'raw_code' => null,
            'diagnostics' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function resolveCustomerName(array $response): ?string
    {
        $name = data_get($response, 'content.Customer_Name')
            ?? data_get($response, 'content.customer_name')
            ?? data_get($response, 'content.CustomerName');

        return $name !== null && $name !== '' ? (string) $name : null;
    }
}
