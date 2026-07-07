<?php

namespace App\Http\Middleware;

use App\Services\Platform\FeatureFlagService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeatureEnabled
{
    public function __construct(
        private readonly FeatureFlagService $featureFlags,
    ) {
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if ($this->featureFlags->isEnabled($feature)) {
            return $next($request);
        }

        return ApiResponse::error(
            message: 'This feature is currently unavailable.',
            errors: ['code' => 'FEATURE_DISABLED', 'feature' => $feature],
            status: 403,
        );
    }
}
