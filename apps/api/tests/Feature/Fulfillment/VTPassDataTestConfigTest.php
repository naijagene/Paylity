<?php

namespace Tests\Feature\Fulfillment;

use App\Services\Fulfillment\VTPassDataTestConfig;
use Tests\TestCase;

class VTPassDataTestConfigTest extends TestCase
{
    public function test_billers_code_falls_back_to_phone_when_unset(): void
    {
        config([
            'services.vtpass.test_data_billers_code' => null,
            'services.vtpass.test_data_phone' => '08011111111',
        ]);

        $this->assertSame('08011111111', VTPassDataTestConfig::billersCode());
        $this->assertSame('08011111111', VTPassDataTestConfig::phone());
    }

    public function test_billers_code_uses_explicit_value_when_set(): void
    {
        config([
            'services.vtpass.test_data_billers_code' => '08022222222',
            'services.vtpass.test_data_phone' => '08011111111',
        ]);

        $this->assertSame('08022222222', VTPassDataTestConfig::billersCode());
        $this->assertSame('08011111111', VTPassDataTestConfig::phone());
    }
}
