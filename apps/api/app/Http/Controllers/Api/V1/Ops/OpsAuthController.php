<?php

namespace App\Http\Controllers\Api\V1\Ops;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OpsAuthController extends Controller
{
    public function validate(): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'authenticated' => true,
                'role' => 'operator',
            ],
            message: 'Operator access verified.',
        );
    }
}
