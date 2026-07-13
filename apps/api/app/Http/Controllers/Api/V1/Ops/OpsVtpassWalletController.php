<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Services\Fulfillment\VtpassWalletBalanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OpsVtpassWalletController extends Controller
{
    public function __construct(
        private readonly VtpassWalletBalanceService $walletBalanceService,
    ) {
    }

    public function refresh(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->walletBalanceService->refresh(),
            message: 'VTPass wallet balance refreshed successfully.',
        );
    }
}
