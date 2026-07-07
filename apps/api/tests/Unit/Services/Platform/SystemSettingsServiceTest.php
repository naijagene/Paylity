<?php

namespace Tests\Unit\Services\Platform;

use App\Models\SystemSetting;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SystemSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SystemSettingsService::class);
    }

    public function test_it_casts_and_caches_settings(): void
    {
        SystemSetting::query()->create([
            'key' => SystemSettingKeys::GUEST_LIMIT,
            'value' => '25000',
            'type' => 'integer',
        ]);

        $this->assertSame(25_000, $this->service->getInt(SystemSettingKeys::GUEST_LIMIT));
        $this->assertSame(25_000, $this->service->getInt(SystemSettingKeys::GUEST_LIMIT));

        SystemSetting::query()
            ->where('key', SystemSettingKeys::GUEST_LIMIT)
            ->update(['value' => '30000']);

        $this->assertSame(25_000, $this->service->getInt(SystemSettingKeys::GUEST_LIMIT));

        $this->service->forgetCache();

        $this->assertSame(30_000, $this->service->getInt(SystemSettingKeys::GUEST_LIMIT));
    }

    public function test_set_many_updates_values_and_invalidates_cache(): void
    {
        SystemSetting::query()->create([
            'key' => SystemSettingKeys::OTP_ENABLED,
            'value' => '1',
            'type' => 'boolean',
        ]);

        $this->assertTrue($this->service->getBool(SystemSettingKeys::OTP_ENABLED));

        $this->service->setMany([
            SystemSettingKeys::OTP_ENABLED => false,
            SystemSettingKeys::GUEST_LIMIT => 15_000,
        ]);

        $this->assertFalse($this->service->getBool(SystemSettingKeys::OTP_ENABLED));
        $this->assertSame(15_000, $this->service->getInt(SystemSettingKeys::GUEST_LIMIT));
        $this->assertSame(15_000, app(SystemSettingsService::class)->getInt(SystemSettingKeys::GUEST_LIMIT));
    }
}
