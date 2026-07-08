<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Platform\HealthCheckService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $report = $this->healthCheckService->report();
        $status = $report['status'];

        $httpStatus = match ($status) {
            'ok' => 200,
            default => 503,
        };

        $message = match ($status) {
            'ok' => 'PAYLITY API is healthy.',
            'degraded' => 'PAYLITY API is running with degraded health.',
            default => 'PAYLITY API is unhealthy.',
        };

        return ApiResponse::success(
            data: $report,
            message: $message,
            status: $httpStatus,
        );
    }
}
