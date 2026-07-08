<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Services\Ops\OpsReportsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsReportsController extends Controller
{
    public function __construct(
        private readonly OpsReportsService $opsReportsService,
    ) {
    }

    public function dailyReconciliation(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsReportsService->dailyReconciliation($request->query('date')),
            message: 'Daily reconciliation report retrieved successfully.',
        );
    }

    public function failedTransactions(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsReportsService->failedTransactions(
                $request->query('date_from'),
                $request->query('date_to'),
            ),
            message: 'Failed transactions report retrieved successfully.',
        );
    }

    public function settlementSummary(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsReportsService->settlementSummary(
                $request->query('date_from'),
                $request->query('date_to'),
            ),
            message: 'Settlement summary retrieved successfully.',
        );
    }

    public function retrySummary(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsReportsService->retrySummary(
                $request->query('date_from'),
                $request->query('date_to'),
            ),
            message: 'Retry summary retrieved successfully.',
        );
    }
}
