<?php

namespace App\Console\Commands;

use App\Support\CorsOriginResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PaylityPreflightCommand extends Command
{
    protected $signature = 'paylity:preflight';

    protected $description = 'Run PAYLITY NG release and staging readiness checks';

    /** @var list<array{status: string, check: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->info('PAYLITY NG — Release Preflight');
        $this->newLine();

        $this->checkAppEnv();
        $this->checkAppDebug();
        $this->checkAppVersion();
        $this->checkAppUrl();
        $this->checkFrontendUrl();
        $this->checkAppKey();
        $this->checkDatabase();
        $this->checkPaystack();
        $this->checkVtpass();
        $this->checkAutoFulfill();
        $this->checkOperatorAccessKey();
        $this->checkCors();
        $this->checkQueueConnection();
        $this->checkLogChannel();

        $this->renderResults();

        $failCount = collect($this->results)->where('status', 'FAIL')->count();

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isDeployedEnvironment(): bool
    {
        return in_array((string) config('app.env'), ['production', 'staging'], true);
    }

    private function checkAppEnv(): void
    {
        $env = (string) config('app.env');

        if ($env === '') {
            $this->record('FAIL', 'APP_ENV', 'APP_ENV is not set.');

            return;
        }

        if (in_array($env, ['production', 'staging'], true)) {
            $this->record('PASS', 'APP_ENV', "APP_ENV={$env}");

            return;
        }

        $this->record('WARN', 'APP_ENV', "APP_ENV={$env} (local/dev — not staging/production)");
    }

    private function checkAppDebug(): void
    {
        $debug = (bool) config('app.debug');
        $env = (string) config('app.env');

        if ($this->isDeployedEnvironment() && $debug) {
            $this->record(
                'FAIL',
                'APP_DEBUG',
                "APP_DEBUG must be false when APP_ENV={$env}.",
            );

            return;
        }

        if ($debug) {
            $this->record('WARN', 'APP_DEBUG', 'APP_DEBUG=true (acceptable for local development only)');

            return;
        }

        $this->record('PASS', 'APP_DEBUG', 'APP_DEBUG=false');
    }

    private function checkAppVersion(): void
    {
        $version = (string) config('app.version');
        $build = (string) config('app.build');

        if ($version === '' || $build === '') {
            $this->record('FAIL', 'Release identity', 'APP_VERSION and APP_BUILD must be set.');

            return;
        }

        if (str_contains($version, 'beta') && $this->isDeployedEnvironment()) {
            $this->record(
                'WARN',
                'Release identity',
                "APP_VERSION={$version} still contains beta on a deployed environment.",
            );

            return;
        }

        $this->record('PASS', 'Release identity', "APP_VERSION={$version}, APP_BUILD={$build}");
    }

    private function checkAppUrl(): void
    {
        $url = rtrim((string) config('app.url'), '/');

        if ($url === '') {
            $this->record(
                $this->isDeployedEnvironment() ? 'FAIL' : 'WARN',
                'APP_URL',
                'APP_URL is not set.',
            );

            return;
        }

        if ($this->isDeployedEnvironment() && $this->isLocalUrl($url)) {
            $this->record('FAIL', 'APP_URL', "APP_URL={$url} is not valid for staging/production.");

            return;
        }

        if ($this->isLocalUrl($url)) {
            $this->record('WARN', 'APP_URL', "APP_URL={$url}");

            return;
        }

        $this->record('PASS', 'APP_URL', "APP_URL={$url}");
    }

    private function checkFrontendUrl(): void
    {
        $url = rtrim((string) config('app.frontend_url'), '/');

        if ($url === '') {
            $this->record(
                $this->isDeployedEnvironment() ? 'FAIL' : 'WARN',
                'FRONTEND_URL',
                'FRONTEND_URL is not set.',
            );

            return;
        }

        if ($this->isDeployedEnvironment() && $this->isLocalUrl($url)) {
            $this->record('FAIL', 'FRONTEND_URL', "FRONTEND_URL={$url} is not valid for staging/production.");

            return;
        }

        if ($this->isLocalUrl($url)) {
            $this->record('WARN', 'FRONTEND_URL', "FRONTEND_URL={$url}");

            return;
        }

        $this->record('PASS', 'FRONTEND_URL', "FRONTEND_URL={$url}");
    }

    private function isLocalUrl(string $url): bool
    {
        return in_array($url, ['http://localhost', 'http://localhost:3000', 'http://127.0.0.1', 'http://127.0.0.1:3000'], true)
            || str_contains($url, 'localhost')
            || str_contains($url, '127.0.0.1');
    }

    private function checkAppKey(): void
    {
        $key = (string) config('app.key');

        if ($key === '') {
            $this->record('FAIL', 'APP_KEY', 'APP_KEY is not set. Run php artisan key:generate.');

            return;
        }

        $this->record('PASS', 'APP_KEY', 'APP_KEY is configured.');
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->record('PASS', 'Database', 'Database connection successful.');
        } catch (\Throwable $exception) {
            $this->record('FAIL', 'Database', 'Database connection failed: '.$exception->getMessage());
        }
    }

    private function checkPaystack(): void
    {
        if (! config('services.paystack.enabled')) {
            $this->record('WARN', 'Paystack', 'FEATURE_PAYSTACK=false (disabled).');

            return;
        }

        $missing = [];

        if (empty(config('services.paystack.secret_key'))) {
            $missing[] = 'PAYSTACK_SECRET_KEY';
        }

        if (empty(config('services.paystack.public_key'))) {
            $missing[] = 'PAYSTACK_PUBLIC_KEY';
        }

        if (empty(config('services.paystack.callback_url'))) {
            $missing[] = 'PAYSTACK_CALLBACK_URL';
        }

        if ($missing !== []) {
            $this->record(
                'FAIL',
                'Paystack',
                'FEATURE_PAYSTACK=true but missing: '.implode(', ', $missing),
            );

            return;
        }

        $this->record('PASS', 'Paystack', 'Paystack keys and callback URL are configured.');
    }

    private function checkVtpass(): void
    {
        if (! config('services.vtpass.enabled')) {
            $this->record('WARN', 'VTPass', 'FEATURE_VTPASS=false (disabled).');

            return;
        }

        $missing = [];

        if (empty(config('services.vtpass.username'))) {
            $missing[] = 'VTPASS_USERNAME';
        }

        if (empty(config('services.vtpass.password'))) {
            $missing[] = 'VTPASS_PASSWORD';
        }

        if (empty(config('services.vtpass.api_key'))) {
            $missing[] = 'VTPASS_API_KEY';
        }

        if ($missing !== []) {
            $this->record(
                'FAIL',
                'VTPass',
                'FEATURE_VTPASS=true but missing: '.implode(', ', $missing),
            );

            return;
        }

        $this->record('PASS', 'VTPass', 'VTPass credentials are configured.');
    }

    private function checkAutoFulfill(): void
    {
        if (! config('services.vtpass.auto_fulfill')) {
            $this->record('PASS', 'Auto-fulfillment', 'FEATURE_VTPASS_AUTO_FULFILL=false (default/manual).');

            return;
        }

        if (config('app.env') === 'staging') {
            $this->record(
                'WARN',
                'Auto-fulfillment',
                'FEATURE_VTPASS_AUTO_FULFILL=true on staging — enable only for intentional delivery testing.',
            );

            return;
        }

        if (config('app.env') === 'production') {
            $this->record(
                'WARN',
                'Auto-fulfillment',
                'FEATURE_VTPASS_AUTO_FULFILL=true in production — confirm VTPass live certification before launch.',
            );

            return;
        }

        $this->record('PASS', 'Auto-fulfillment', 'FEATURE_VTPASS_AUTO_FULFILL=true (local/dev).');
    }

    private function checkOperatorAccessKey(): void
    {
        if (empty(config('services.operator.access_key'))) {
            $this->record('FAIL', 'Operator access', 'OPERATOR_ACCESS_KEY is not set.');

            return;
        }

        $this->record('PASS', 'Operator access', 'OPERATOR_ACCESS_KEY is configured.');
    }

    private function checkCors(): void
    {
        $origins = CorsOriginResolver::allowedOrigins();

        if ($this->isDeployedEnvironment() && in_array('*', $origins, true)) {
            $this->record('FAIL', 'CORS', 'Wildcard origin is not allowed in staging/production.');

            return;
        }

        if ($this->isDeployedEnvironment() && empty(config('app.frontend_url'))) {
            $this->record('FAIL', 'CORS', 'FRONTEND_URL must be set in staging/production.');

            return;
        }

        if ($origins === []) {
            $this->record('WARN', 'CORS', 'No allowed origins configured.');

            return;
        }

        $this->record(
            'PASS',
            'CORS',
            'Allowed origins: '.implode(', ', $origins),
        );
    }

    private function checkQueueConnection(): void
    {
        $connection = (string) config('queue.default');

        if ($connection === '' || $connection === 'sync') {
            $status = $this->isDeployedEnvironment() ? 'WARN' : 'PASS';
            $this->record(
                $status,
                'Queue',
                "QUEUE_CONNECTION={$connection}",
            );

            return;
        }

        $this->record('PASS', 'Queue', "QUEUE_CONNECTION={$connection}");
    }

    private function checkLogChannel(): void
    {
        $channel = (string) config('logging.default');

        if ($channel === '') {
            $this->record('FAIL', 'Logging', 'LOG_CHANNEL is not configured.');

            return;
        }

        $this->record('PASS', 'Logging', "LOG_CHANNEL={$channel}");
    }

    private function record(string $status, string $check, string $detail): void
    {
        $this->results[] = [
            'status' => $status,
            'check' => $check,
            'detail' => $detail,
        ];
    }

    private function renderResults(): void
    {
        $rows = collect($this->results)->map(fn (array $result) => [
            $result['status'],
            $result['check'],
            $result['detail'],
        ])->all();

        $this->table(['Status', 'Check', 'Detail'], $rows);

        $pass = collect($this->results)->where('status', 'PASS')->count();
        $warn = collect($this->results)->where('status', 'WARN')->count();
        $fail = collect($this->results)->where('status', 'FAIL')->count();

        $this->newLine();
        $this->line("Summary: {$pass} PASS, {$warn} WARN, {$fail} FAIL");

        if ($fail > 0) {
            $this->error('Preflight failed. Resolve FAIL items before staging/production deployment.');
        } elseif ($warn > 0) {
            $this->warn('Preflight passed with warnings. Review before deployment.');
        } else {
            $this->info('Preflight passed.');
        }
    }
}
