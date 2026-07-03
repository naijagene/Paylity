<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BuildInfoService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __construct(
        private readonly BuildInfoService $buildInfoService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $buildInfo = $this->buildInfoService->all();
        $databaseHealthy = $this->checkDatabase();

        return ApiResponse::success(
            data: [
                'status' => $databaseHealthy ? 'ok' : 'degraded',
                'application' => $buildInfo['application'],
                'version' => $buildInfo['version'],
                'environment' => $buildInfo['environment'],
                'build' => $buildInfo['build'],
                'current_time' => now()->toIso8601String(),
                'checks' => [
                    'database' => $databaseHealthy ? 'ok' : 'failed',
                ],
            ],
            message: $databaseHealthy
                ? 'PAYLITY API is healthy.'
                : 'PAYLITY API is running with degraded health.',
        );
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
