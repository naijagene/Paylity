<?php

namespace Tests\Concerns;

use Database\Seeders\PlatformSettingsSeeder;

trait SeedsPlatformSettings
{
    protected function seedPlatformSettings(): void
    {
        $this->seed(PlatformSettingsSeeder::class);
    }
}
