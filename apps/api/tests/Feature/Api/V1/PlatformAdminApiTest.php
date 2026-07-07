<?php

namespace Tests\Feature\Api\V1;

use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.operator.access_key' => self::OPERATOR_KEY]);
        $this->seed(PlatformSettingsSeeder::class);
    }

    public function test_settings_endpoints_require_operator_key(): void
    {
        $this->getJson('/api/v1/settings')->assertUnauthorized();
    }

    public function test_it_lists_and_updates_system_settings(): void
    {
        $listResponse = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/settings');

        $listResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['key' => SystemSettingKeys::GUEST_LIMIT]);

        $updateResponse = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->putJson('/api/v1/settings', [
            'settings' => [
                SystemSettingKeys::GUEST_LIMIT => 18_000,
                SystemSettingKeys::OTP_ENABLED => false,
            ],
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.'.SystemSettingKeys::GUEST_LIMIT, 18_000)
            ->assertJsonPath('data.'.SystemSettingKeys::OTP_ENABLED, false);
    }

    public function test_it_lists_and_updates_feature_flags(): void
    {
        $listResponse = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/feature-flags');

        $listResponse
            ->assertOk()
            ->assertJsonFragment(['key' => FeatureFlagKeys::WALLET]);

        $updateResponse = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->putJson('/api/v1/feature-flags', [
            'flags' => [
                FeatureFlagKeys::WALLET => true,
                FeatureFlagKeys::REFERRAL => true,
            ],
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.'.FeatureFlagKeys::WALLET, true)
            ->assertJsonPath('data.'.FeatureFlagKeys::REFERRAL, true);
    }
}
