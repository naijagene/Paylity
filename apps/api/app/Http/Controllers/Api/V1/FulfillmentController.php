<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\FulfillmentException;
use App\Exceptions\VTPassConfigurationException;
use App\Exceptions\VTPassException;
use App\Http\Controllers\Controller;
use App\Services\Fulfillment\FulfillmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FulfillmentController extends Controller
{
    public function __construct(
        private readonly FulfillmentService $fulfillmentService,
    ) {
    }

    public function fulfill(string $reference): JsonResponse
    {
        if (! $this->fulfillmentService->isEnabled()) {
            return ApiResponse::error(
                message: 'VTPass fulfillment is disabled.',
                errors: ['code' => 'VTPASS_DISABLED'],
                status: 503,
            );
        }

        try {
            $transaction = $this->fulfillmentService->fulfillByReference($reference);

            return ApiResponse::success(
                data: $this->fulfillmentService->toResponse($transaction),
                message: 'Fulfillment completed successfully.',
            );
        } catch (FulfillmentException $exception) {
            $status = $exception->errorCode === 'TRANSACTION_NOT_FOUND' ? 404 : 422;

            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: $status,
            );
        } catch (VTPassConfigurationException|VTPassException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: 502,
            );
        }
    }
}
