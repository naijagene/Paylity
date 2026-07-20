<?php

namespace App\Services\Launch;

use App\Enums\LedgerAccountCode;
use App\Models\DailyFinancialSnapshot;
use App\Models\LedgerAccount;
use App\Models\ProviderService;
use App\Models\ProviderVariation;
use App\Models\Transaction;
use App\Services\Finance\FinancialAlertService;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Fulfillment\VtpassWalletBalanceService;
use App\Services\Ops\OpsReliabilityService;
use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\HealthCheckService;
use App\Services\Platform\SystemSettingsService;
use App\Services\TransactionReferenceGenerator;
use App\Support\CorsOriginResolver;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PaymentLivePreflightService
{
    public const STATUS_READY = 'READY';

    public const STATUS_READY_WITH_WARNINGS = 'READY_WITH_WARNINGS';

    public const STATUS_BLOCKED = 'BLOCKED';

    public function __construct(
        private readonly PaystackModeInspector $paystackModeInspector,
        private readonly VtpassModeInspector $vtpassModeInspector,
        private readonly LaunchModeService $launchModeService,
        private readonly SchedulerHeartbeatService $schedulerHeartbeatService,
        private readonly FinancialLedgerService $financialLedgerService,
        private readonly HealthCheckService $healthCheckService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly OpsReliabilityService $opsReliabilityService,
        private readonly VtpassWalletBalanceService $walletBalanceService,
        private readonly FinancialAlertService $financialAlertService,
        private readonly TransactionReferenceGenerator $referenceGenerator,
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(bool $strict = false, ?string $reference = null, bool $persist = true): array
    {
        $checks = $this->buildChecks($strict, $reference);
        $status = $this->resolveStatus($checks, $strict);
        $paystack = $this->paystackModeInspector->inspect();

        $report = [
            'verdict' => $status,
            'status' => $status,
            'strict' => $strict,
            'environment' => (string) config('app.env'),
            'launch_mode' => $this->launchModeService->mode(),
            'paystack_mode' => $paystack['detected_mode'] ?? 'unknown',
            'vtpass_mode' => ($this->vtpassModeInspector->inspect()['mode'] ?? 'unknown'),
            'callback_url' => $paystack['callback_url'] ?? '',
            'webhook_url' => $paystack['webhook_url'] ?? '',
            'summary' => $this->summarize($checks),
            'checks' => $checks,
            'paystack' => $this->sanitizePaystackReport($paystack),
            'daily_usage' => $this->launchModeService->dailyUsage(),
        ];

        if ($persist) {
            $this->settings->set(SystemSettingKeys::PAYMENT_LIVE_PREFLIGHT_LAST_RUN_AT, now()->toIso8601String());
            $this->settings->set(SystemSettingKeys::PAYMENT_LIVE_PREFLIGHT_LAST_STATUS, $status);
        }

        return $report;
    }

    /**
     * @return list<array<string, string>>
     */
    private function buildChecks(bool $strict, ?string $reference): array
    {
        $health = $this->healthCheckService->report();
        $healthChecks = is_array($health['checks'] ?? null) ? $health['checks'] : [];
        $paystack = $this->paystackModeInspector->inspect();
        $vtpass = $this->vtpassModeInspector->inspect();
        $scheduler = $this->schedulerHeartbeatService->snapshot();
        $wallet = $this->walletBalanceService->snapshot();
        $reliability = $this->opsReliabilityService->snapshot();
        $reconcile = is_array($reliability['reconciliation'] ?? null) ? $reliability['reconciliation'] : [];
        $launchMode = $this->launchModeService->mode();
        $dailyUsage = $this->launchModeService->dailyUsage();
        $appEnv = (string) config('app.env');
        $isProduction = $appEnv === 'production';
        $financeAlerts = $this->financialAlertService->scan(dryRun: true);

        $checks = [
            $this->check('app_env', 'APP_ENV', $appEnv !== '' ? 'PASS' : 'FAIL', 'APP_ENV='.$appEnv),
            $this->check(
                'app_debug',
                'APP_DEBUG',
                ! ((bool) config('app.debug')) || ! $isProduction ? 'PASS' : 'FAIL',
                (bool) config('app.debug') ? 'APP_DEBUG=true' : 'APP_DEBUG=false',
            ),
            $this->check(
                'https',
                'HTTPS',
                str_starts_with((string) config('app.url'), 'https://') ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                str_starts_with((string) config('app.url'), 'https://')
                    ? 'APP_URL uses HTTPS.'
                    : 'APP_URL is not configured with HTTPS.',
            ),
            $this->check(
                'paystack_mode_live',
                'Paystack Live Mode',
                ($paystack['detected_mode'] ?? '') === PaystackModeInspector::MODE_LIVE ? 'PASS' : 'FAIL',
                'Detected Paystack mode: '.($paystack['detected_mode'] ?? 'unknown'),
            ),
            $this->check(
                'paystack_mode_alignment',
                'Paystack Key Alignment',
                ($paystack['detected_mode'] ?? '') === PaystackModeInspector::MODE_MIXED_INVALID
                    ? 'FAIL'
                    : ((($paystack['verdict'] ?? PaystackModeInspector::VERDICT_INVALID) === PaystackModeInspector::VERDICT_VALID) ? 'PASS' : 'FAIL'),
                ($paystack['public_key_mode'] ?? 'missing').' / '.($paystack['secret_key_mode'] ?? 'missing'),
            ),
            $this->check(
                'callback_url',
                'Customer Callback URL',
                ($paystack['callback_url'] ?? '') !== '' ? 'PASS' : 'FAIL',
                ($paystack['callback_url'] ?? '') !== '' ? 'Callback URL configured.' : 'PAYSTACK_CALLBACK_URL missing.',
            ),
            $this->check(
                'webhook_url',
                'API Webhook URL',
                ($paystack['webhook_url'] ?? '') !== '' ? 'PASS' : 'FAIL',
                $paystack['webhook_url'] ?? 'Webhook URL unavailable.',
            ),
            $this->check(
                'callback_route',
                'Callback Route',
                $this->callbackRouteExists() ? 'PASS' : 'FAIL',
                $this->callbackRouteExists() ? 'Paystack callback route registered.' : 'Callback route missing.',
            ),
            $this->check(
                'webhook_route',
                'Webhook Route',
                ($paystack['webhook_route_exists'] ?? false) ? 'PASS' : 'FAIL',
                ($paystack['webhook_route_exists'] ?? false) ? 'Webhook route registered.' : 'Webhook route missing.',
            ),
            $this->check(
                'webhook_signature',
                'Webhook Signature Verification',
                ($paystack['webhook_signature_verification_enabled'] ?? false) ? 'PASS' : 'FAIL',
                ($paystack['webhook_signature_verification_enabled'] ?? false)
                    ? 'Webhook signature verification enabled.'
                    : 'Paystack secret key required for webhook verification.',
            ),
            $this->check(
                'paystack_reachability',
                'Paystack Verification Reachability',
                $this->paystackReachabilityStatus($strict),
                $this->paystackReachabilityMessage(),
            ),
            $this->check(
                'database_writable',
                'Database Writable',
                ($healthChecks['database'] ?? 'failed') === 'ok' ? 'PASS' : 'FAIL',
                ($healthChecks['database'] ?? 'failed') === 'ok'
                    ? 'Database connection healthy.'
                    : 'Database connectivity failed.',
            ),
            $this->check(
                'cache_writable',
                'Cache Writable',
                ($healthChecks['cache'] ?? 'failed') === 'ok' ? 'PASS' : 'FAIL',
                ($healthChecks['cache'] ?? 'failed') === 'ok' ? 'Cache probe succeeded.' : 'Cache probe failed.',
            ),
            $this->check(
                'queue_health',
                'Queue Health',
                $this->queueStatus($healthChecks['queue'] ?? null, $strict),
                $this->queueMessage($healthChecks['queue'] ?? null),
            ),
            $this->check(
                'scheduler_health',
                'Scheduler Health',
                match ($scheduler['status'] ?? SchedulerHeartbeatService::STATUS_UNKNOWN) {
                    SchedulerHeartbeatService::STATUS_HEALTHY => 'PASS',
                    SchedulerHeartbeatService::STATUS_WARNING => 'WARN',
                    default => $strict ? 'FAIL' : 'WARN',
                },
                'Scheduler heartbeat: '.($scheduler['status'] ?? 'unknown'),
            ),
            $this->check(
                'reference_generation',
                'Transaction Reference Generation',
                preg_match('/^PYL-\d{8}-[A-Z0-9]{6}$/', $this->referenceGenerator->generate()) === 1 ? 'PASS' : 'FAIL',
                'Reference generator produces PAYLITY format references.',
            ),
            $this->check(
                'ledger_accounts',
                'Finance Ledger Accounts',
                $this->ledgerHealthy() ? 'PASS' : 'FAIL',
                $this->ledgerHealthy()
                    ? 'Ledger accounts seeded and balanced.'
                    : 'Ledger accounts missing or imbalanced.',
            ),
            $this->check(
                'settlement_reconciliation',
                'Settlement Reconciliation',
                is_array($reconcile) ? 'PASS' : 'FAIL',
                'Payment reconciliation service available.',
            ),
            $this->check(
                'vtpass_mode',
                'VTPass Mode',
                'PASS',
                'VTPass mode: '.($vtpass['mode'] ?? 'unknown'),
            ),
            $this->check(
                'vtpass_wallet',
                'VTPass Wallet Balance',
                match ($wallet['health'] ?? 'unknown') {
                    'healthy' => 'PASS',
                    'warning' => 'WARN',
                    default => $strict ? 'FAIL' : 'WARN',
                },
                'Wallet health: '.($wallet['health'] ?? 'unknown').', balance: '.($wallet['balance'] ?? '—'),
            ),
            $this->check(
                'product_catalog',
                'Product Catalog',
                $this->catalogHealthy() ? 'PASS' : 'FAIL',
                $this->catalogHealthy()
                    ? 'Catalog has active airtime, data, and electricity products.'
                    : 'Catalog missing required active products.',
            ),
            $this->check(
                'feature_flags',
                'Feature Flags',
                $this->featureFlagsHealthy() ? 'PASS' : 'WARN',
                $this->featureFlagsHealthy()
                    ? 'Core feature flags configured.'
                    : 'Feature flags need review.',
            ),
            $this->check(
                'launch_mode',
                'Launch Mode',
                in_array($launchMode, [LaunchModeService::MODE_SOFT_LAUNCH, LaunchModeService::MODE_LIVE], true)
                    ? 'PASS'
                    : 'FAIL',
                'Launch mode: '.$launchMode,
            ),
            $this->check(
                'daily_transaction_cap',
                'Daily Transaction Cap',
                $dailyUsage['transaction_limit_daily'] > 0 ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                'Daily transaction cap: '.$dailyUsage['transaction_limit_daily'],
            ),
            $this->check(
                'daily_revenue_cap',
                'Daily Revenue Cap',
                $dailyUsage['revenue_limit_daily'] > 0 ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                'Daily revenue cap (naira): '.$dailyUsage['revenue_limit_daily'],
            ),
            $this->check(
                'daily_usage',
                'Daily Usage Below Limits',
                $this->dailyUsageWithinLimits($dailyUsage) ? 'PASS' : 'FAIL',
                sprintf(
                    'Transactions %d/%d, revenue %d/%d naira.',
                    $dailyUsage['transaction_count'],
                    $dailyUsage['transaction_limit_daily'],
                    $dailyUsage['gross_collection_naira'],
                    $dailyUsage['revenue_limit_daily'],
                ),
            ),
            $this->check(
                'maintenance_mode',
                'Maintenance Mode',
                $launchMode !== LaunchModeService::MODE_MAINTENANCE ? 'PASS' : 'FAIL',
                $launchMode === LaunchModeService::MODE_MAINTENANCE
                    ? 'Maintenance mode is active.'
                    : 'Maintenance mode inactive.',
            ),
            $this->check(
                'database_backup',
                'Recent Database Backup',
                $this->backupHealthy($strict),
                $this->backupMessage(),
            ),
            $this->check(
                'financial_close',
                'Recent Financial Close',
                $this->financialCloseHealthy($strict),
                $this->financialCloseMessage(),
            ),
            $this->check(
                'finance_alerts',
                'Critical Finance Alerts',
                ($financeAlerts['totals']['critical'] ?? 0) === 0 ? 'PASS' : 'FAIL',
                'Critical finance alerts: '.($financeAlerts['totals']['critical'] ?? 0),
            ),
            $this->check(
                'cors_origins',
                'CORS Production Domains',
                $this->corsHealthy($strict),
                $this->corsMessage(),
            ),
        ];

        if ($strict && ($paystack['blockers'] ?? []) !== []) {
            $checks[] = $this->check(
                'paystack_blockers',
                'Paystack Configuration Blockers',
                'FAIL',
                implode(' ', $paystack['blockers']),
            );
        }

        if ($reference !== null && $reference !== '') {
            $checks[] = $this->check(
                'reference_lookup',
                'Certification Reference',
                Schema::hasTable('transactions')
                    && Transaction::query()->where('reference', $reference)->exists()
                    ? 'PASS'
                    : 'WARN',
                Schema::hasTable('transactions')
                    && Transaction::query()->where('reference', $reference)->exists()
                    ? "Reference {$reference} found."
                    : "Reference {$reference} not found yet.",
            );
        }

        return $checks;
    }

    private function callbackRouteExists(): bool
    {
        return collect(Route::getRoutes())->contains(
            fn ($route) => in_array('GET', $route->methods(), true)
                && str_contains($route->uri(), 'payments/paystack/callback'),
        );
    }

    private function paystackReachabilityStatus(bool $strict): string
    {
        if (! (bool) config('services.paystack.enabled') || ! config('services.paystack.secret_key')) {
            return 'FAIL';
        }

        try {
            $base = rtrim((string) config('services.paystack.base_url'), '/');
            $response = Http::withToken((string) config('services.paystack.secret_key'))
                ->acceptJson()
                ->timeout(8)
                ->get($base.'/transaction/verify/PYL-PREFLIGHT-PROBE');

            return $response->status() > 0 ? 'PASS' : ($strict ? 'FAIL' : 'WARN');
        } catch (\Throwable) {
            return $strict ? 'FAIL' : 'WARN';
        }
    }

    private function paystackReachabilityMessage(): string
    {
        if (! config('services.paystack.secret_key')) {
            return 'Paystack secret key not configured.';
        }

        try {
            $base = rtrim((string) config('services.paystack.base_url'), '/');
            Http::withToken((string) config('services.paystack.secret_key'))
                ->acceptJson()
                ->timeout(8)
                ->get($base.'/transaction/verify/PYL-PREFLIGHT-PROBE');

            return 'Paystack verification endpoint reachable.';
        } catch (\Throwable $exception) {
            return 'Paystack verification endpoint unreachable: '.$exception->getMessage();
        }
    }

    /**
     * @param  array<string, int|float|null>  $dailyUsage
     */
    private function dailyUsageWithinLimits(array $dailyUsage): bool
    {
        $transactionLimit = (int) ($dailyUsage['transaction_limit_daily'] ?? 0);
        $revenueLimit = (int) ($dailyUsage['revenue_limit_daily'] ?? 0);

        if ($transactionLimit > 0 && (int) $dailyUsage['transaction_count'] >= $transactionLimit) {
            return false;
        }

        if ($revenueLimit > 0 && (int) $dailyUsage['gross_collection_naira'] >= $revenueLimit) {
            return false;
        }

        return true;
    }

    private function backupHealthy(bool $strict): string
    {
        $lastRun = $this->settings->getString(SystemSettingKeys::BACKUP_LAST_RUN_AT);
        $verifiedAt = $this->settings->getString(SystemSettingKeys::BACKUP_LAST_VERIFIED_AT);

        if ($lastRun === '' || $verifiedAt === '') {
            return $strict ? 'FAIL' : 'WARN';
        }

        return 'PASS';
    }

    private function backupMessage(): string
    {
        $lastRun = $this->settings->getString(SystemSettingKeys::BACKUP_LAST_RUN_AT);
        $verifiedAt = $this->settings->getString(SystemSettingKeys::BACKUP_LAST_VERIFIED_AT);

        if ($lastRun === '') {
            return 'No database backup recorded.';
        }

        if ($verifiedAt === '') {
            return 'Backup exists but has not been verified.';
        }

        return 'Backup recorded and verified.';
    }

    private function financialCloseHealthy(bool $strict): string
    {
        if (! Schema::hasTable('daily_financial_snapshots')) {
            return $strict ? 'FAIL' : 'WARN';
        }

        $recent = DailyFinancialSnapshot::query()
            ->whereNotNull('finalized_at')
            ->where('finalized_at', '>=', now()->subDays(2))
            ->exists();

        return $recent ? 'PASS' : ($strict ? 'FAIL' : 'WARN');
    }

    private function financialCloseMessage(): string
    {
        if (! Schema::hasTable('daily_financial_snapshots')) {
            return 'Financial close snapshots unavailable.';
        }

        $lastClose = DailyFinancialSnapshot::query()
            ->whereNotNull('finalized_at')
            ->orderByDesc('finalized_at')
            ->value('finalized_at');

        return $lastClose
            ? 'Last financial close: '.$lastClose->toIso8601String()
            : 'No financial close recorded.';
    }

    private function corsHealthy(bool $strict): string
    {
        $origins = CorsOriginResolver::allowedOrigins();
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        if ($frontend === '') {
            return $strict ? 'FAIL' : 'WARN';
        }

        $hasFrontend = in_array($frontend, $origins, true);
        $hasOpsOrigin = collect($origins)->contains(
            fn (string $origin) => str_contains(strtolower($origin), 'ops'),
        );

        if ((string) config('app.env') === 'production') {
            return $hasFrontend && $hasOpsOrigin ? 'PASS' : ($strict ? 'FAIL' : 'WARN');
        }

        return $hasFrontend ? 'PASS' : ($strict ? 'FAIL' : 'WARN');
    }

    private function corsMessage(): string
    {
        $origins = CorsOriginResolver::allowedOrigins();

        return 'Allowed origins: '.implode(', ', $origins);
    }

    private function ledgerHealthy(): bool
    {
        $accounts = Schema::hasTable('ledger_accounts') ? LedgerAccount::query()->count() : 0;

        return $accounts > 0
            && count($this->financialLedgerService->imbalanceTransactions()) === 0
            && $this->financialLedgerService->accountBalance(LedgerAccountCode::PAYSTACK_CLEARING) !== [];
    }

    private function catalogHealthy(): bool
    {
        $airtimeNetworks = Schema::hasTable('provider_services')
            ? ProviderService::query()->where('category_key', 'airtime')->where('is_active', true)->count()
            : 0;
        $dataProducts = Schema::hasTable('provider_variations')
            ? ProviderVariation::query()->where('is_active', true)->count()
            : 0;
        $electricityProviders = Schema::hasTable('provider_services')
            ? ProviderService::query()->where('category_key', 'electricity')->where('is_active', true)->count()
            : 0;

        return $airtimeNetworks > 0 && $dataProducts > 0 && $electricityProviders > 0;
    }

    private function featureFlagsHealthy(): bool
    {
        return $this->featureFlagService->all() !== [];
    }

    /**
     * @param  string|array<string, mixed>|null  $queue
     */
    private function queueStatus(string|array|null $queue, bool $strict): string
    {
        if (is_string($queue)) {
            return match ($queue) {
                'ok' => 'PASS',
                'degraded' => $strict ? 'FAIL' : 'WARN',
                default => 'FAIL',
            };
        }

        if (! is_array($queue)) {
            return 'WARN';
        }

        return match ($queue['status'] ?? 'failed') {
            'ok' => 'PASS',
            'degraded' => $strict ? 'FAIL' : 'WARN',
            default => 'FAIL',
        };
    }

    /**
     * @param  string|array<string, mixed>|null  $queue
     */
    private function queueMessage(string|array|null $queue): string
    {
        if (is_string($queue)) {
            return "Queue health: {$queue}";
        }

        if (! is_array($queue)) {
            return 'Queue status unavailable.';
        }

        return sprintf(
            'Queue connection=%s, pending=%d, failed=%d',
            $queue['connection'] ?? 'unknown',
            (int) ($queue['pending_jobs'] ?? 0),
            (int) ($queue['failed_jobs'] ?? 0),
        );
    }

    /**
     * @param  list<array<string, string>>  $checks
     */
    private function resolveStatus(array $checks, bool $strict): string
    {
        $hasFail = collect($checks)->contains(fn (array $check) => $check['status'] === 'FAIL');
        $hasWarn = collect($checks)->contains(fn (array $check) => $check['status'] === 'WARN');

        if ($hasFail || ($strict && $hasWarn)) {
            return self::STATUS_BLOCKED;
        }

        if ($hasWarn) {
            return self::STATUS_READY_WITH_WARNINGS;
        }

        return self::STATUS_READY;
    }

    /**
     * @param  list<array<string, string>>  $checks
     * @return array{pass: int, warn: int, fail: int}
     */
    private function summarize(array $checks): array
    {
        return [
            'pass' => (int) collect($checks)->where('status', 'PASS')->count(),
            'warn' => (int) collect($checks)->where('status', 'WARN')->count(),
            'fail' => (int) collect($checks)->where('status', 'FAIL')->count(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function check(string $key, string $name, string $status, string $detail): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'status' => $status,
            'detail' => $detail,
            'severity' => match ($status) {
                'FAIL' => 'critical',
                'WARN' => 'warning',
                default => 'info',
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $paystack
     * @return array<string, mixed>
     */
    private function sanitizePaystackReport(array $paystack): array
    {
        unset($paystack['secret_configured']);

        return $paystack;
    }
}
