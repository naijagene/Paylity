<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOperatorKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = (string) config('services.operator.access_key');

        if ($configuredKey === '') {
            return ApiResponse::error(
                message: 'Operations access is not configured.',
                errors: ['code' => 'OPERATOR_ACCESS_NOT_CONFIGURED'],
                status: 401,
            );
        }

        $providedKey = (string) $request->header('X-Operator-Key', '');

        if ($providedKey === '' || ! hash_equals($configuredKey, $providedKey)) {
            return ApiResponse::error(
                message: 'Invalid or missing operator access key.',
                errors: ['code' => 'OPERATOR_ACCESS_DENIED'],
                status: 401,
            );
        }

        return $next($request);
    }
}
