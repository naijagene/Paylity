<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSystemSettingsRequest;
use App\Models\FeatureFlag;
use App\Models\SystemSetting;
use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\SystemSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingsService $settingsService,
    ) {
    }

    public function index(): JsonResponse
    {
        $settings = SystemSetting::query()
            ->orderBy('key')
            ->get()
            ->map(fn (SystemSetting $setting): array => [
                'key' => $setting->key,
                'value' => $this->settingsService->get($setting->key),
                'type' => $setting->type,
                'description' => $setting->description,
                'updated_at' => $setting->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return ApiResponse::success(
            data: $settings,
            message: 'System settings retrieved successfully.',
        );
    }

    public function update(UpdateSystemSettingsRequest $request): JsonResponse
    {
        $this->settingsService->setMany($request->validated('settings'));

        return ApiResponse::success(
            data: $this->settingsService->all(),
            message: 'System settings updated successfully.',
        );
    }
}
