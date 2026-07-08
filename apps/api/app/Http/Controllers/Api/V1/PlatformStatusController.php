<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformStatusService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlatformStatusController extends Controller
{
    public function __construct(
        private readonly PlatformStatusService $platformStatusService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: $this->platformStatusService->publicStatus(),
            message: 'Platform status retrieved successfully.',
        );
    }
}
