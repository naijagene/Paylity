<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_success_response(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'PAYLITY API is healthy.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'application',
                    'version',
                    'environment',
                    'build',
                    'current_time',
                ],
            ])
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.version', config('app.version'))
            ->assertJsonPath('data.build', config('app.build'));
    }
}
