<?php

namespace Tests\Unit\Services\Platform;

use App\Models\FeatureFlag;
use App\Services\Platform\FeatureFlagService;
use App\Support\Platform\FeatureFlagKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeatureFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeatureFlagService::class);
    }

    public function test_it_reads_and_caches_feature_flags(): void
    {
        FeatureFlag::query()->create([
            'key' => FeatureFlagKeys::WALLET,
            'enabled' => true,
        ]);

        $this->assertTrue($this->service->isEnabled(FeatureFlagKeys::WALLET));
        $this->assertTrue($this->service->isEnabled(FeatureFlagKeys::WALLET));

        FeatureFlag::query()
            ->where('key', FeatureFlagKeys::WALLET)
            ->update(['enabled' => false]);

        $this->assertTrue($this->service->isEnabled(FeatureFlagKeys::WALLET));

        $this->service->forgetCache();

        $this->assertFalse($this->service->isEnabled(FeatureFlagKeys::WALLET));
    }

    public function test_environment_override_takes_precedence_for_integrated_flags(): void
    {
        FeatureFlag::query()->create([
            'key' => FeatureFlagKeys::PAYSTACK,
            'enabled' => false,
        ]);

        putenv('FEATURE_PAYSTACK=true');
        $_ENV['FEATURE_PAYSTACK'] = 'true';

        $this->service->forgetCache();

        $this->assertTrue($this->service->isEnabled(FeatureFlagKeys::PAYSTACK));

        putenv('FEATURE_PAYSTACK');
        unset($_ENV['FEATURE_PAYSTACK']);
    }

    public function test_it_falls_back_to_default_when_flag_is_not_seeded(): void
    {
        $this->assertFalse($this->service->isEnabled(FeatureFlagKeys::LOYALTY));
        $this->assertTrue($this->service->isEnabled(FeatureFlagKeys::PAYSTACK, true));
    }
}
