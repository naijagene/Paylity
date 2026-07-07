<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\SystemSetting;
use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_rc1_platform_defaults(): void
    {
        $this->seed(PlatformSettingsSeeder::class);

        $this->assertTrue(
            (bool) SystemSetting::query()->where('key', SystemSettingKeys::GUEST_CHECKOUT_ENABLED)->value('value'),
        );
        $this->assertTrue(
            (bool) SystemSetting::query()->where('key', SystemSettingKeys::OTP_ENABLED)->value('value'),
        );
        $this->assertSame(
            '20000',
            SystemSetting::query()->where('key', SystemSettingKeys::GUEST_LIMIT)->value('value'),
        );
        $this->assertSame(
            '10000',
            SystemSetting::query()->where('key', SystemSettingKeys::OTP_THRESHOLD)->value('value'),
        );
        $this->assertSame(
            '20000',
            SystemSetting::query()->where('key', SystemSettingKeys::REGISTRATION_THRESHOLD)->value('value'),
        );

        $this->assertFalse(
            FeatureFlag::query()->where('key', FeatureFlagKeys::CUSTOMER_ACCOUNTS)->value('enabled'),
        );
        $this->assertFalse(
            FeatureFlag::query()->where('key', FeatureFlagKeys::WALLET)->value('enabled'),
        );
        $this->assertFalse(
            FeatureFlag::query()->where('key', FeatureFlagKeys::REFERRAL)->value('enabled'),
        );
        $this->assertFalse(
            FeatureFlag::query()->where('key', FeatureFlagKeys::LOYALTY)->value('enabled'),
        );
        $this->assertFalse(
            FeatureFlag::query()->where('key', FeatureFlagKeys::SAVED_BENEFICIARIES)->value('enabled'),
        );
        $this->assertFalse(
            FeatureFlag::query()->where('key', FeatureFlagKeys::VIRTUAL_ACCOUNTS)->value('enabled'),
        );
    }
}
