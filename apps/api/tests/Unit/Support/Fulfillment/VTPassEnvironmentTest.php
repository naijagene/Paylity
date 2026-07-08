<?php

namespace Tests\Unit\Support\Fulfillment;

use App\Support\Fulfillment\VTPassEnvironment;
use Tests\TestCase;

class VTPassEnvironmentTest extends TestCase
{
    public function test_sandbox_mode_uses_sandbox_base_url_by_default(): void
    {
        config([
            'services.vtpass.environment' => 'sandbox',
            'services.vtpass.base_url' => 'https://sandbox.vtpass.com',
        ]);

        $this->assertSame('sandbox', VTPassEnvironment::mode());
        $this->assertTrue(VTPassEnvironment::isSandbox());
        $this->assertSame('https://sandbox.vtpass.com', VTPassEnvironment::configuredBaseUrl());
        $this->assertTrue(VTPassEnvironment::baseUrlMatchesMode());
    }

    public function test_production_mode_defaults_to_live_base_url(): void
    {
        config([
            'services.vtpass.environment' => 'production',
            'services.vtpass.base_url' => 'https://vtpass.com',
        ]);

        $this->assertTrue(VTPassEnvironment::isProduction());
        $this->assertSame('https://vtpass.com', VTPassEnvironment::configuredBaseUrl());
        $this->assertTrue(VTPassEnvironment::baseUrlMatchesMode());
    }

    public function test_production_mode_fails_when_base_url_points_to_sandbox(): void
    {
        config([
            'services.vtpass.environment' => 'production',
            'services.vtpass.base_url' => 'https://sandbox.vtpass.com',
        ]);

        $this->assertFalse(VTPassEnvironment::baseUrlMatchesMode());
    }
}
