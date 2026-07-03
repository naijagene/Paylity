<?php

namespace Tests\Feature\Fulfillment;

use App\Services\Fulfillment\VTPassElectricityTestConfig;
use Tests\TestCase;

class VTPassElectricityTestConfigTest extends TestCase
{
    public function test_prefers_electricity_specific_env_values(): void
    {
        config([
            'services.vtpass.test_electricity_disco' => 'ikeja-electric',
            'services.vtpass.test_electricity_meter_number' => '11111111111',
            'services.vtpass.test_electricity_meter_type' => 'postpaid',
            'services.vtpass.test_disco' => 'EKEDC',
            'services.vtpass.test_meter_number' => '99999999999',
            'services.vtpass.test_meter_type' => 'prepaid',
        ]);

        $this->assertSame('ikeja-electric', VTPassElectricityTestConfig::disco());
        $this->assertSame('11111111111', VTPassElectricityTestConfig::meterNumber());
        $this->assertSame('postpaid', VTPassElectricityTestConfig::meterType());
        $this->assertTrue(VTPassElectricityTestConfig::isConfigured());
    }

    public function test_falls_back_to_legacy_env_values_when_electricity_values_missing(): void
    {
        config([
            'services.vtpass.test_electricity_disco' => null,
            'services.vtpass.test_electricity_meter_number' => null,
            'services.vtpass.test_electricity_meter_type' => null,
            'services.vtpass.test_disco' => 'IKEDC',
            'services.vtpass.test_meter_number' => '45053854956',
            'services.vtpass.test_meter_type' => 'prepaid',
        ]);

        $this->assertSame('IKEDC', VTPassElectricityTestConfig::disco());
        $this->assertSame('45053854956', VTPassElectricityTestConfig::meterNumber());
        $this->assertSame('prepaid', VTPassElectricityTestConfig::meterType());
        $this->assertTrue(VTPassElectricityTestConfig::isConfigured());
    }

    public function test_defaults_disco_to_ikedc_when_no_values_are_set(): void
    {
        config([
            'services.vtpass.test_electricity_disco' => null,
            'services.vtpass.test_disco' => null,
            'services.vtpass.test_electricity_meter_number' => null,
            'services.vtpass.test_meter_number' => null,
        ]);

        $this->assertSame('IKEDC', VTPassElectricityTestConfig::disco());
        $this->assertFalse(VTPassElectricityTestConfig::isConfigured());
    }
}
