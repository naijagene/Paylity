<?php

namespace Tests\Feature\Api\V1;

use App\Services\Platform\HealthCheckService;
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
                    'checks' => [
                        'api',
                        'database',
                        'cache',
                        'queue',
                        'mail',
                        'paystack',
                        'vtpass',
                    ],
                    'environment_validation',
                ],
            ])
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.checks.api', 'ok')
            ->assertJsonPath('data.checks.database', 'ok')
            ->assertJsonPath('data.version', config('app.version'))
            ->assertJsonPath('data.build', config('app.build'));
    }

    public function test_health_endpoint_returns_service_unavailable_when_database_check_fails(): void
    {
        $this->mock(HealthCheckService::class, function ($mock): void {
            $mock->shouldReceive('report')->andReturn([
                'status' => 'unhealthy',
                'application' => 'PAYLITY NG API',
                'version' => config('app.version'),
                'environment' => config('app.env'),
                'build' => config('app.build'),
                'current_time' => now()->toIso8601String(),
                'checks' => [
                    'api' => 'ok',
                    'database' => 'failed',
                    'cache' => 'ok',
                    'queue' => ['status' => 'ok', 'connection' => 'sync'],
                    'mail' => 'ok',
                    'paystack' => 'skipped',
                    'vtpass' => 'skipped',
                ],
                'environment_validation' => ['pass' => 1, 'warn' => 0, 'fail' => 0],
            ]);
        });

        $response = $this->getJson('/api/v1/health');

        $response
            ->assertStatus(503)
            ->assertJsonPath('data.status', 'unhealthy')
            ->assertJsonPath('data.checks.database', 'failed');
    }
}
