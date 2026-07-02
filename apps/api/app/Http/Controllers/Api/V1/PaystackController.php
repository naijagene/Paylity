<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaystackController extends Controller
{
    public function callback(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: ['status' => 'Paystack integration coming next.'],
            message: 'Paystack integration coming next.',
        );
    }

    public function webhook(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: ['status' => 'Paystack integration coming next.'],
            message: 'Paystack integration coming next.',
        );
    }
}
