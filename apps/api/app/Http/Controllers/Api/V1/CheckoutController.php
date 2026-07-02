<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\FraudCheckException;
use App\Exceptions\PaystackConfigurationException;
use App\Exceptions\PaystackException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InitializeCheckoutRequest;
use App\Services\TransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {
    }

    public function initialize(InitializeCheckoutRequest $request): JsonResponse
    {
        try {
            $transaction = $this->transactionService->initializeCheckout(
                input: $request->validated(),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (FraudCheckException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: 422,
            );
        } catch (PaystackConfigurationException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: 422,
            );
        } catch (PaystackException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: 502,
            );
        }

        return ApiResponse::success(
            data: $this->transactionService->toCheckoutResponse($transaction),
            message: 'Checkout initialized successfully.',
            status: 201,
        );
    }
}
