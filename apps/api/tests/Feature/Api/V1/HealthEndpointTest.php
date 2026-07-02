<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'data' => [
                    'service' => 'PAYLITY NG API',
                    'status' => 'ok',
                ],
            ]);
    }
}
