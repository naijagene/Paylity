<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsProductCatalog;
use Tests\TestCase;

class ElectricityMeterVerifyTest extends TestCase
{
    use RefreshDatabase;
    use SeedsProductCatalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedProductCatalog();
    }

    public function test_meter_verify_requires_valid_payload(): void
    {
        $this->postJson('/api/v1/electricity/meter/verify', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['disco', 'meter_number', 'meter_type']);
    }

    public function test_meter_verify_returns_unavailable_when_vtpass_disabled(): void
    {
        config(['services.vtpass.enabled' => false]);

        $response = $this->postJson('/api/v1/electricity/meter/verify', [
            'disco' => 'IKEDC',
            'meter_number' => '12345678901',
            'meter_type' => 'prepaid',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.disco', 'IKEDC')
            ->assertJsonPath('data.meter_number', '12345678901');
    }

    public function test_meter_verify_rejects_invalid_meter_type(): void
    {
        $this->postJson('/api/v1/electricity/meter/verify', [
            'disco' => 'IKEDC',
            'meter_number' => '12345678901',
            'meter_type' => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['meter_type']);
    }
}
