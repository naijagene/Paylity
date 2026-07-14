<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\LaunchVoucherException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ValidateVoucherRequest;
use App\Services\Marketing\LaunchVoucherService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class LaunchVoucherController extends Controller
{
    public function __construct(
        private readonly LaunchVoucherService $launchVoucherService,
    ) {
    }

    public function validateVoucher(ValidateVoucherRequest $request): JsonResponse
    {
        try {
            $result = $this->launchVoucherService->validateForCheckout($request->validated());
        } catch (LaunchVoucherException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: $exception->status,
            );
        }

        return ApiResponse::success(
            data: $result,
            message: 'Voucher validated successfully.',
        );
    }
}
