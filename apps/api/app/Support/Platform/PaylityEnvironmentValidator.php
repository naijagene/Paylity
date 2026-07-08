<?php

namespace App\Support\Platform;

use App\Support\CorsOriginResolver;
use App\Support\Fulfillment\VTPassEnvironment;
use Illuminate\Support\Facades\DB;

class PaylityEnvironmentValidator
{
    /** @var list<array{status: string, check: string, detail: string}> */
    private array $results = [];

    /**
     * @return list<array{status: string, check: string, detail: string}>
     */
    public function validate(): array
    {
        $this->results = [];

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
        $this->checkSessionSecurity();
        $this->checkMailConfiguration();

        return $this->results;
    }

    public function hasFailures(): bool
    {
        return collect($this->results)->contains(fn (array $result): bool => $result['status'] === 'FAIL');
    }

    /**
     * @return array{pass: int, warn: int, fail: int}
     */
    public function summary(): array
    {
        return [
            'pass' => (int) collect($this->results)->where('status', 'PASS')->count(),
            'warn' => (int) collect($this->results)->where('status', 'WARN')->count(),
            'fail' => (int) collect($this->results)->where('status', 'FAIL')->count(),
        ];
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
        $mode = VTPassEnvironment::mode();

        foreach (['username', 'password', 'api_key'] as $credential) {
            if (empty(config("services.vtpass.{$credential}"))) {
                $missing[] = 'VTPASS_'.strtoupper($credential === 'api_key' ? 'API_KEY' : $credential);
            }
        }

        if ($mode === VTPassEnvironment::PRODUCTION) {
            foreach (['public_key', 'secret_key'] as $credential) {
                if (empty(config("services.vtpass.{$credential}"))) {
                    $missing[] = 'VTPASS_'.strtoupper($credential);
                }
            }
        }

        if ($missing !== []) {
            $this->record(
                'FAIL',
                'VTPass',
                'FEATURE_VTPASS=true but missing: '.implode(', ', $missing),
            );

            return;
        }

        if ($mode === VTPassEnvironment::PRODUCTION && ! VTPassEnvironment::baseUrlMatchesMode()) {
            $this->record(
                'FAIL',
                'VTPass environment',
                'VTPASS_ENV=production but VTPASS_BASE_URL does not point to the live VTPass host.',
            );

            return;
        }

        if ($mode === VTPassEnvironment::SANDBOX && ! VTPassEnvironment::baseUrlMatchesMode()) {
            $this->record(
                'WARN',
                'VTPass environment',
                'VTPASS_ENV=sandbox but VTPASS_BASE_URL does not point to sandbox.vtpass.com.',
            );
        } else {
            $this->record(
                'PASS',
                'VTPass environment',
                'VTPASS_ENV='.$mode.', host='.VTPassEnvironment::baseUrlHost(),
            );
        }

        if ($mode === VTPassEnvironment::SANDBOX) {
            $optionalMissing = [];

            if (empty(config('services.vtpass.public_key'))) {
                $optionalMissing[] = 'VTPASS_PUBLIC_KEY';
            }

            if (empty(config('services.vtpass.secret_key'))) {
                $optionalMissing[] = 'VTPASS_SECRET_KEY';
            }

            if ($optionalMissing !== []) {
                $this->record(
                    'WARN',
                    'VTPass keys',
                    'Sandbox missing optional keys: '.implode(', ', $optionalMissing),
                );
            }
        }

        if ($mode === VTPassEnvironment::PRODUCTION && empty(config('services.vtpass.public_key'))) {
            $this->record(
                'WARN',
                'VTPass balance',
                'VTPASS_PUBLIC_KEY is required for automated wallet balance checks in production.',
            );
        } else {
            $this->record('PASS', 'VTPass balance', 'Wallet balance checks can use GET /api/balance when VTPass is reachable.');
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

    private function checkSessionSecurity(): void
    {
        if (! $this->isDeployedEnvironment()) {
            $this->record('PASS', 'Session cookies', 'Session security checks skipped in local development.');

            return;
        }

        $secure = config('session.secure');
        $sameSite = (string) config('session.same_site', 'lax');

        if ($secure !== true) {
            $this->record(
                'WARN',
                'Session cookies',
                'SESSION_SECURE_COOKIE should be true in staging/production.',
            );

            return;
        }

        $this->record('PASS', 'Session cookies', "secure=true, same_site={$sameSite}");
    }

    private function checkMailConfiguration(): void
    {
        $mailer = (string) config('mail.default');

        if ($mailer === '') {
            $this->record('FAIL', 'Mail', 'MAIL_MAILER is not configured.');

            return;
        }

        if ($this->isDeployedEnvironment() && $mailer === 'log') {
            $this->record('WARN', 'Mail', 'MAIL_MAILER=log on a deployed environment.');

            return;
        }

        $this->record('PASS', 'Mail', "MAIL_MAILER={$mailer}");
    }

    private function record(string $status, string $check, string $detail): void
    {
        $this->results[] = [
            'status' => $status,
            'check' => $check,
            'detail' => $detail,
        ];
    }
}
