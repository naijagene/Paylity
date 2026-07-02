<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'service' => 'PAYLITY NG API',
                'status' => 'ok',
            ],
            message: 'PAYLITY API is healthy.',
        );
    }
}
