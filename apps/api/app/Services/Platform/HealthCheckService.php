<?php

namespace App\Services\Platform;

use App\Services\BuildInfoService;
use App\Support\Platform\PaylityEnvironmentValidator;
use App\Support\Fulfillment\VTPassEnvironment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthCheckService
{
    public function __construct(
        private readonly BuildInfoService $buildInfoService,
        private readonly PaylityEnvironmentValidator $environmentValidator,
    ) {
    }

    /**
     * @return array{
     *     status: string,
     *     application: string,
     *     version: string,
     *     environment: string,
     *     build: string,
     *     current_time: string,
     *     checks: array<string, string|array<string, mixed>>,
     *     environment_validation?: array{pass: int, warn: int, fail: int}
     * }
     */
    public function report(): array
    {
        $buildInfo = $this->buildInfoService->all();
        $checks = [
            'api' => 'ok',
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'mail' => $this->checkMail(),
            'paystack' => $this->checkPaystack(),
            'vtpass' => $this->checkVtpass(),
        ];

        $status = $this->resolveOverallStatus($checks);
        $envSummary = $this->environmentValidator->summary();

        return [
            'status' => $status,
            'application' => $buildInfo['application'],
            'version' => $buildInfo['version'],
            'environment' => $buildInfo['environment'],
            'build' => $buildInfo['build'],
            'current_time' => now()->toIso8601String(),
            'checks' => $checks,
            'environment_validation' => $envSummary,
        ];
    }

    public function isHealthy(): bool
    {
        return $this->report()['status'] === 'ok';
    }

    /**
     * @param  array<string, string|array<string, mixed>>  $checks
     */
    private function resolveOverallStatus(array $checks): string
    {
        if ($checks['database'] !== 'ok') {
            return 'unhealthy';
        }

        foreach ($checks as $key => $value) {
            if ($key === 'api') {
                continue;
            }

            if ($value === 'failed') {
                return 'degraded';
            }

            if (is_array($value) && ($value['status'] ?? null) === 'degraded') {
                return 'degraded';
            }
        }

        return 'ok';
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (\Throwable) {
            return 'failed';
        }
    }

    private function checkCache(): string
    {
        try {
            $key = 'health.check.'.uniqid('', true);
            Cache::put($key, 'ok', 10);

            return Cache::get($key) === 'ok' ? 'ok' : 'failed';
        } catch (\Throwable) {
            return 'failed';
        }
    }

    /**
     * @return array{status: string, connection: string, pending_jobs?: int, failed_jobs?: int}
     */
    private function checkQueue(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $result = [
            'status' => 'ok',
            'connection' => $connection,
        ];

        if ($connection === '' || $connection === 'sync') {
            $result['status'] = in_array((string) config('app.env'), ['production', 'staging'], true)
                ? 'degraded'
                : 'ok';

            return $result;
        }

        try {
            DB::connection(config("queue.connections.{$connection}.connection") ?? config('database.default'))
                ->getPdo();
        } catch (\Throwable) {
            $result['status'] = 'failed';

            return $result;
        }

        if ($connection === 'database' && Schema::hasTable('jobs')) {
            $result['pending_jobs'] = (int) DB::table('jobs')->count();
        }

        if (Schema::hasTable('failed_jobs')) {
            $result['failed_jobs'] = (int) DB::table('failed_jobs')->count();
        }

        if (($result['failed_jobs'] ?? 0) > 0) {
            $result['status'] = 'degraded';
        }

        return $result;
    }

    private function checkMail(): string
    {
        $mailer = (string) config('mail.default', '');

        if ($mailer === '') {
            return 'failed';
        }

        if (
            in_array((string) config('app.env'), ['production', 'staging'], true)
            && $mailer === 'log'
        ) {
            return 'degraded';
        }

        return 'ok';
    }

    private function checkPaystack(): string
    {
        if (! config('services.paystack.enabled')) {
            return 'skipped';
        }

        $configured = ! empty(config('services.paystack.secret_key'))
            && ! empty(config('services.paystack.public_key'))
            && ! empty(config('services.paystack.callback_url'));

        return $configured ? 'ok' : 'failed';
    }

    private function checkVtpass(): string
    {
        if (! config('services.vtpass.enabled')) {
            return 'skipped';
        }

        $configured = ! empty(config('services.vtpass.username'))
            && ! empty(config('services.vtpass.password'))
            && ! empty(config('services.vtpass.api_key'));

        if (! $configured) {
            return 'failed';
        }

        if (VTPassEnvironment::isProduction() && ! VTPassEnvironment::baseUrlMatchesMode()) {
            return 'failed';
        }

        return 'ok';
    }
}
