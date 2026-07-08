<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Services\Ops\OpsDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OpsDashboardController extends Controller
{
    public function __construct(
        private readonly OpsDashboardService $opsDashboardService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->opsDashboardService->snapshot(),
            message: 'Operations dashboard snapshot retrieved successfully.',
        );
    }
}
