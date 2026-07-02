<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BuildInfoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        private readonly BuildInfoService $buildInfoService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $buildInfo = $this->buildInfoService->all();

        return ApiResponse::success(
            data: [
                'status' => 'ok',
                'application' => $buildInfo['application'],
                'version' => $buildInfo['version'],
                'environment' => $buildInfo['environment'],
                'build' => $buildInfo['build'],
                'current_time' => now()->toIso8601String(),
            ],
            message: 'PAYLITY API is healthy.',
        );
    }
}
