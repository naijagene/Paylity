<?php

namespace App\Console\Commands;

use App\Support\CorsOriginResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PaylityPreflightCommand extends Command
{
    protected $signature = 'paylity:preflight';

    protected $description = 'Run PAYLITY NG pre-launch environment checks';

    /** @var list<array{status: string, check: string, detail: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->info('PAYLITY NG — Pre-Launch Preflight');
        $this->newLine();

        $this->checkAppEnv();
        $this->checkAppDebug();
        $this->checkAppUrl();
        $this->checkAppKey();
        $this->checkDatabase();
        $this->checkPaystack();
        $this->checkVtpass();
        $this->checkOperatorAccessKey();
        $this->checkCors();
        $this->checkQueueConnection();
        $this->checkLogChannel();

        $this->renderResults();

        $failCount = collect($this->results)->where('status', 'FAIL')->count();

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkAppEnv(): void
    {
        $env = (string) config('app.env');

        if ($env === '') {
            $this->record('FAIL', 'APP_ENV', 'APP_ENV is not set.');

            return;
        }

        if ($env === 'production') {
            $this->record('PASS', 'APP_ENV', 'APP_ENV=production');

            return;
        }

        $this->record('WARN', 'APP_ENV', "APP_ENV={$env} (not production)");
    }

    private function checkAppDebug(): void
    {
        $debug = (bool) config('app.debug');
        $isProduction = config('app.env') === 'production';

        if ($isProduction && $debug) {
            $this->record('FAIL', 'APP_DEBUG', 'APP_DEBUG must be false in production.');

            return;
        }

        if ($debug) {
            $this->record('WARN', 'APP_DEBUG', 'APP_DEBUG=true (acceptable for local/staging only)');

            return;
        }

        $this->record('PASS', 'APP_DEBUG', 'APP_DEBUG=false');
    }

    private function checkAppUrl(): void
    {
        $url = (string) config('app.url');

        if ($url === '' || $url === 'http://localhost') {
            $this->record(
                config('app.env') === 'production' ? 'FAIL' : 'WARN',
                'APP_URL',
                $url === '' ? 'APP_URL is not set.' : "APP_URL={$url}",
            );

            return;
        }

        $this->record('PASS', 'APP_URL', "APP_URL={$url}");
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
        $isProduction = config('app.env') === 'production';

        if ($isProduction && in_array('*', $origins, true)) {
            $this->record('FAIL', 'CORS', 'Wildcard origin is not allowed in production.');

            return;
        }

        if ($isProduction && empty(config('app.frontend_url'))) {
            $this->record('FAIL', 'CORS', 'FRONTEND_URL must be set in production.');

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
            $status = config('app.env') === 'production' ? 'WARN' : 'PASS';
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
            $this->error('Preflight failed. Resolve FAIL items before production launch.');
        } elseif ($warn > 0) {
            $this->warn('Preflight passed with warnings. Review before launch.');
        } else {
            $this->info('Preflight passed.');
        }
    }
}
