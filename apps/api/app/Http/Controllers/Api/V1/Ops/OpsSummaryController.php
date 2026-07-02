<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Services\Ops\OpsTransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OpsSummaryController extends Controller
{
    public function __construct(
        private readonly OpsTransactionService $opsTransactionService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsTransactionService->summaryForToday(),
            message: 'Operations summary retrieved successfully.',
        );
    }
}
