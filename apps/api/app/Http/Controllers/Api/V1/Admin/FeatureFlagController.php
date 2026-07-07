<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateFeatureFlagsRequest;
use App\Models\FeatureFlag;
use App\Services\Platform\FeatureFlagService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FeatureFlagController extends Controller
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
    ) {
    }

    public function index(): JsonResponse
    {
        $flags = FeatureFlag::query()
            ->orderBy('key')
            ->get()
            ->map(fn (FeatureFlag $flag): array => [
                'key' => $flag->key,
                'enabled' => $this->featureFlagService->isEnabled($flag->key, (bool) $flag->enabled),
                'stored_enabled' => (bool) $flag->enabled,
                'description' => $flag->description,
                'updated_at' => $flag->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ApiResponse::success(
            data: $flags,
            message: 'Feature flags retrieved successfully.',
        );
    }

    public function update(UpdateFeatureFlagsRequest $request): JsonResponse
    {
        foreach ($request->validated('flags') as $key => $enabled) {
            $this->featureFlagService->set($key, (bool) $enabled);
        }

        return ApiResponse::success(
            data: $this->featureFlagService->all(),
            message: 'Feature flags updated successfully.',
        );
    }
}
