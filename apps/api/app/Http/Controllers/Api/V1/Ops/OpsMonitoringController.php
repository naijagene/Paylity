<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Services\Ops\OpsMonitoringService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpsMonitoringController extends Controller
{
    public function __construct(
        private readonly OpsMonitoringService $opsMonitoringService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsMonitoringService->summary(
                $request->query('date_from'),
                $request->query('date_to'),
            ),
            message: 'Operations monitoring summary retrieved successfully.',
        );
    }
}
