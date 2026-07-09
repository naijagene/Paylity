<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\VerifyElectricityMeterRequest;
use App\Services\Fulfillment\ElectricityMeterVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ElectricityMeterController extends Controller
{
    public function __construct(
        private readonly ElectricityMeterVerificationService $meterVerificationService,
    ) {
    }

    public function verify(VerifyElectricityMeterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->meterVerificationService->verify(
            (string) $validated['disco'],
            (string) $validated['meter_number'],
            (string) $validated['meter_type'],
        );

        $message = $result['verified']
            ? 'Meter verified successfully.'
            : ($result['available']
                ? ($result['message'] ?: 'Meter verification failed.')
                : ($result['message'] ?: 'Meter verification is currently unavailable.'));

        return ApiResponse::success(
            data: [
                'verified' => $result['verified'],
                'available' => $result['available'],
                'customer_name' => $result['customer_name'],
                'meter_number' => $result['meter_number'],
                'disco' => $result['disco'],
                'message' => $result['message'],
                'minimum_amount' => $result['minimum_amount'],
            ],
            message: $message,
        );
    }
}
