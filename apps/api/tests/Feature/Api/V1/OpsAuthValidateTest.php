<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OpsAuthValidateTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATOR_KEY = 'test-operator-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.operator.access_key' => self::OPERATOR_KEY]);
    }

    public function test_validate_returns_authenticated_for_valid_key(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/auth/validate');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.role', 'operator')
            ->assertJsonMissingPath('data.access_key')
            ->assertJsonMissingPath('data.operator_key');

        $this->assertStringNotContainsString(
            self::OPERATOR_KEY,
            $response->getContent(),
        );
    }

    public function test_validate_rejects_missing_key(): void
    {
        $this->getJson('/api/v1/ops/auth/validate')
            ->assertUnauthorized()
            ->assertJsonPath('errors.code', 'OPERATOR_ACCESS_DENIED');
    }

    public function test_validate_rejects_invalid_key(): void
    {
        Log::spy();

        $this->withHeaders([
            'X-Operator-Key' => 'wrong-key',
        ])->getJson('/api/v1/ops/auth/validate')
            ->assertUnauthorized()
            ->assertJsonPath('errors.code', 'OPERATOR_ACCESS_DENIED');

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Operator access denied.'
                    && isset($context['ip'], $context['path'])
                    && ! isset($context['key'], $context['operator_key'], $context['X-Operator-Key']);
            });
    }

    public function test_validate_does_not_return_configured_operator_key(): void
    {
        $response = $this->withHeaders([
            'X-Operator-Key' => self::OPERATOR_KEY,
        ])->getJson('/api/v1/ops/auth/validate');

        $response->assertOk();

        $payload = $response->json();

        $this->assertNotContains(self::OPERATOR_KEY, $payload);
    }

    public function test_validate_is_rate_limited_by_ip(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->withHeaders([
                'X-Operator-Key' => 'wrong-key',
            ])->getJson('/api/v1/ops/auth/validate');
        }

        $this->withHeaders([
            'X-Operator-Key' => 'wrong-key',
        ])->getJson('/api/v1/ops/auth/validate')
            ->assertStatus(429)
            ->assertJsonPath('errors.code', 'RATE_LIMIT_EXCEEDED');
    }
}
