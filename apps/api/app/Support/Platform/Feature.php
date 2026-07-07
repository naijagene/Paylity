<?php

namespace App\Support\Platform;

use App\Services\Platform\FeatureFlagService;

class Feature
{
    public static function enabled(string $key, bool $default = false): bool
    {
        return app(FeatureFlagService::class)->isEnabled($key, $default);
    }
}
