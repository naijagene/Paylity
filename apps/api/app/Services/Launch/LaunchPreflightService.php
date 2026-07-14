<?php

namespace App\Services\Launch;

use App\Models\LedgerAccount;
use App\Models\ProviderService;
use App\Models\ProviderVariation;
use App\Enums\LedgerAccountCode;
use App\Models\TransactionFinancial;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Fulfillment\VtpassWalletBalanceService;
use App\Services\Ops\OpsReliabilityService;
use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\HealthCheckService;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LaunchPreflightService
{
    public const STATUS_READY = 'READY';

    public const STATUS_READY_WITH_WARNINGS = 'READY_WITH_WARNINGS';

    public const STATUS_BLOCKED = 'BLOCKED';

    public function __construct(
        private readonly PaystackModeInspector $paystackModeInspector,
        private readonly VtpassModeInspector $vtpassModeInspector,
        private readonly SchedulerHeartbeatService $schedulerHeartbeatService,
        private readonly PricingAuditService $pricingAuditService,
        private readonly DatabaseFingerprintService $databaseFingerprintService,
        private readonly FinancialLedgerService $financialLedgerService,
        private readonly HealthCheckService $healthCheckService,
        private readonly FeatureFlagService $featureFlagService,
        private readonly OpsReliabilityService $opsReliabilityService,
        private readonly VtpassWalletBalanceService $walletBalanceService,
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(
        string $environment = 'production',
        bool $strict = false,
        bool $checkExternal = false,
        ?string $reference = null,
    ): array {
        $checks = $this->standardChecks($environment, $strict);

        if ($reference) {
            $checks[] = $this->referenceCheck($reference);
        }

        $status = $this->resolveStatus($checks, $strict);
        $summary = $this->summarize($checks);

        $this->settings->set(SystemSettingKeys::PREFLIGHT_LAST_RUN_AT, now()->toIso8601String());
        $this->settings->set(SystemSettingKeys::PREFLIGHT_LAST_STATUS, $status);
        $this->settings->set(SystemSettingKeys::PREFLIGHT_LAST_CHECKS, json_encode($checks) ?: '[]');

        return [
            'status' => $status,
            'environment' => $environment,
            'strict' => $strict,
            'check_external' => $checkExternal,
            'build' => (string) config('app.build'),
            'version' => (string) config('app.version'),
            'summary' => $summary,
            'checks' => $checks,
            'database_fingerprint' => $this->databaseFingerprintService->fingerprint(),
            'paystack_mode' => $this->paystackModeInspector->inspect(),
            'vtpass_mode' => $this->vtpassModeInspector->inspect(),
            'scheduler' => $this->schedulerHeartbeatService->snapshot(),
            'pricing_audit' => $this->pricingAuditService->audit(),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function standardChecks(string $environment, bool $strict): array
    {
        $health = $this->healthCheckService->report();
        $healthChecks = is_array($health['checks'] ?? null) ? $health['checks'] : [];
        $fingerprint = $this->databaseFingerprintService->fingerprint();
        $paystack = $this->paystackModeInspector->inspect();
        $vtpass = $this->vtpassModeInspector->inspect();
        $scheduler = $this->schedulerHeartbeatService->snapshot();
        $wallet = $this->walletBalanceService->snapshot();
        $reliability = $this->opsReliabilityService->snapshot();
        $reconcile = is_array($reliability['reconciliation'] ?? null) ? $reliability['reconciliation'] : [];
        $isProduction = $environment === 'production';

        $checks = [
            $this->namedCheck(
                'Database',
                ($healthChecks['database'] ?? 'failed') === 'ok' && ($fingerprint['writable'] ?? false) ? 'PASS' : 'FAIL',
                ($healthChecks['database'] ?? 'failed') === 'ok'
                    ? 'Database connection is healthy and writable.'
                    : 'Database connectivity or write access failed.',
            ),
            $this->namedCheck(
                'Cache',
                ($healthChecks['cache'] ?? 'failed') === 'ok' ? 'PASS' : 'FAIL',
                ($healthChecks['cache'] ?? 'failed') === 'ok' ? 'Cache read/write probe succeeded.' : 'Cache probe failed.',
            ),
            $this->namedCheck(
                'Routes',
                $this->criticalRoutesRegistered() ? 'PASS' : 'FAIL',
                $this->criticalRoutesRegistered()
                    ? 'Core API routes are registered.'
                    : 'One or more core API routes are missing.',
            ),
            $this->namedCheck(
                'Queue',
                $this->queueStatus($healthChecks['queue'] ?? null, $strict),
                $this->queueMessage($healthChecks['queue'] ?? null),
            ),
            $this->namedCheck(
                'Scheduler',
                match ($scheduler['status'] ?? SchedulerHeartbeatService::STATUS_UNKNOWN) {
                    SchedulerHeartbeatService::STATUS_HEALTHY => 'PASS',
                    SchedulerHeartbeatService::STATUS_WARNING => 'WARN',
                    default => $strict ? 'FAIL' : 'WARN',
                },
                'Scheduler heartbeat status: '.($scheduler['status'] ?? 'unknown'),
            ),
            $this->namedCheck(
                'Storage',
                $this->storageWritable() ? 'PASS' : 'FAIL',
                $this->storageWritable() ? 'Application storage paths are writable.' : 'Storage paths are not writable.',
            ),
            $this->namedCheck(
                'Permissions',
                is_writable(storage_path('logs')) && is_writable(storage_path('app')) ? 'PASS' : 'FAIL',
                'Log and app storage directories must be writable.',
            ),
            $this->namedCheck(
                'HTTPS',
                str_starts_with((string) config('app.url'), 'https://') ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                str_starts_with((string) config('app.url'), 'https://')
                    ? 'APP_URL uses HTTPS.'
                    : 'APP_URL is not configured with HTTPS.',
            ),
            $this->namedCheck(
                'APP_DEBUG',
                ! ((bool) config('app.debug')) || ! $isProduction ? 'PASS' : 'FAIL',
                (bool) config('app.debug')
                    ? 'APP_DEBUG=true (must be false in production).'
                    : 'APP_DEBUG=false.',
            ),
            $this->namedCheck(
                'APP_ENV',
                in_array((string) config('app.env'), ['production', 'staging'], true) ? 'PASS' : 'WARN',
                'APP_ENV='.(string) config('app.env'),
            ),
            $this->namedCheck(
                'Paystack Mode',
                $isProduction && ($paystack['mode'] ?? '') === 'test' ? ($strict ? 'FAIL' : 'WARN') : 'PASS',
                'Detected Paystack mode: '.($paystack['mode'] ?? 'unknown'),
            ),
            $this->namedCheck(
                'VTPass Mode',
                $isProduction && ($vtpass['mode'] ?? 'sandbox') === 'sandbox' ? ($strict ? 'FAIL' : 'WARN') : 'PASS',
                'Detected VTPass mode: '.($vtpass['mode'] ?? 'unknown'),
            ),
            $this->namedCheck(
                'Wallet Balance',
                match ($wallet['health'] ?? 'unknown') {
                    'healthy', 'unknown' => 'PASS',
                    'warning' => 'WARN',
                    default => $strict ? 'FAIL' : 'WARN',
                },
                'Wallet health: '.($wallet['health'] ?? 'unknown').', balance: '.($wallet['balance'] ?? '—'),
            ),
            $this->namedCheck(
                'Callback URL',
                ($paystack['callback_url'] ?? '') !== '' ? 'PASS' : 'FAIL',
                ($paystack['callback_url'] ?? '') !== ''
                    ? 'Callback URL configured.'
                    : 'PAYSTACK_CALLBACK_URL is missing.',
            ),
            $this->namedCheck(
                'Webhook URL',
                ($paystack['webhook_route_exists'] ?? false) ? 'PASS' : 'FAIL',
                ($paystack['webhook_route_exists'] ?? false)
                    ? 'Paystack webhook route is registered.'
                    : 'Paystack webhook route is missing.',
            ),
            $this->namedCheck(
                'Ledger',
                $this->ledgerHealthy() ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                $this->ledgerHealthy()
                    ? 'Ledger accounts are seeded and balanced.'
                    : 'Ledger accounts missing or imbalanced entries detected.',
            ),
            $this->namedCheck(
                'Settlement',
                abs((int) $this->financialLedgerService->accountBalance(LedgerAccountCode::SETTLEMENT_DIFFERENCE)['balance_kobo']) === 0
                    ? 'PASS'
                    : 'WARN',
                'Settlement difference balance kobo: '.((int) $this->financialLedgerService->accountBalance(LedgerAccountCode::SETTLEMENT_DIFFERENCE)['balance_kobo']),
            ),
            $this->namedCheck(
                'Finance',
                $this->financeHealthy($strict),
                $this->financeMessage(),
            ),
            $this->namedCheck(
                'Reconciliation',
                (($reconcile['stale_payment_pending'] ?? 0) + ($reconcile['paid_unfulfilled'] ?? 0) + ($reconcile['stale_fulfillment_pending'] ?? 0)) === 0 ? 'PASS' : 'WARN',
                sprintf(
                    'Stale payment pending: %d, paid unfulfilled: %d, stale fulfillment: %d.',
                    (int) ($reconcile['stale_payment_pending'] ?? 0),
                    (int) ($reconcile['paid_unfulfilled'] ?? 0),
                    (int) ($reconcile['stale_fulfillment_pending'] ?? 0),
                ),
            ),
            $this->namedCheck(
                'Catalog',
                $this->catalogHealthy() ? 'PASS' : 'FAIL',
                $this->catalogHealthy()
                    ? 'Catalog has active airtime, data, and electricity products.'
                    : 'Catalog is missing one or more required active products.',
            ),
            $this->namedCheck(
                'Feature Flags',
                $this->featureFlagsHealthy() ? 'PASS' : 'WARN',
                $this->featureFlagsHealthy()
                    ? 'Core feature flags are configured.'
                    : 'One or more feature flags need review before launch.',
            ),
        ];

        $audit = $this->pricingAuditService->audit();
        if (! ($audit['all_positive'] ?? false)) {
            $checks[] = $this->namedCheck(
                'Pricing Audit',
                $strict ? 'FAIL' : 'WARN',
                'Negative margin launch amounts: '.($audit['negative_margin_count'] ?? 0),
            );
        }

        $lastRun = $this->settings->getString(SystemSettingKeys::BACKUP_LAST_RUN_AT);
        if ($lastRun === '') {
            $checks[] = $this->namedCheck(
                'Backup',
                $strict ? 'FAIL' : 'WARN',
                'No database backup recorded.',
            );
        }

        return $checks;
    }

    private function criticalRoutesRegistered(): bool
    {
        $hasWebhook = collect(Route::getRoutes())->contains(
            fn ($route) => in_array('POST', $route->methods(), true)
                && str_contains($route->uri(), 'payments/paystack/webhook'),
        );
        $hasGoLive = collect(Route::getRoutes())->contains(
            fn ($route) => in_array('GET', $route->methods(), true)
                && str_contains($route->uri(), 'ops/go-live'),
        );

        return $hasWebhook && $hasGoLive;
    }

    private function storageWritable(): bool
    {
        return is_writable(storage_path()) && is_writable(storage_path('app'));
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

    private function ledgerHealthy(): bool
    {
        $accounts = Schema::hasTable('ledger_accounts') ? LedgerAccount::query()->count() : 0;

        return $accounts > 0 && count($this->financialLedgerService->imbalanceTransactions()) === 0;
    }

    private function financeHealthy(bool $strict): string
    {
        $negativeMargins = Schema::hasTable('transaction_financials')
            ? TransactionFinancial::query()->where('gross_margin_kobo', '<', 0)->count()
            : 0;

        if ($negativeMargins > 0) {
            return $strict ? 'FAIL' : 'WARN';
        }

        return 'PASS';
    }

    private function financeMessage(): string
    {
        $negativeMargins = Schema::hasTable('transaction_financials')
            ? TransactionFinancial::query()->where('gross_margin_kobo', '<', 0)->count()
            : 0;

        return "Transactions with negative gross margin: {$negativeMargins}";
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
        $flags = $this->featureFlagService->all();

        return $flags !== [];
    }

    /**
     * @return array<string, string>
     */
    private function referenceCheck(string $reference): array
    {
        $exists = Schema::hasTable('transactions')
            && DB::table('transactions')->where('reference', $reference)->exists();

        return $this->namedCheck(
            'Smoke Reference',
            $exists ? 'PASS' : 'WARN',
            $exists ? "Reference {$reference} exists in database." : "Reference {$reference} was not found.",
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
     * @return array{name: string, status: string, message: string, severity: string, category: string, check: string, detail: string}
     */
    private function namedCheck(string $name, string $status, string $message): array
    {
        return $this->normalizeCheck(
            category: Str::slug($name, '_'),
            check: Str::slug($name, '_'),
            status: $status,
            detail: $message,
            name: $name,
        );
    }

    /**
     * @return array{name: string, status: string, message: string, severity: string, category: string, check: string, detail: string}
     */
    private function normalizeCheck(
        string $category,
        string $check,
        string $status,
        string $detail,
        ?string $name = null,
    ): array {
        $displayName = $name ?? Str::title(str_replace('_', ' ', $check));

        return [
            'name' => $displayName,
            'status' => $status,
            'message' => $detail,
            'severity' => $this->severityForStatus($status),
            'category' => $category,
            'check' => $check,
            'detail' => $detail,
        ];
    }

    private function severityForStatus(string $status): string
    {
        return match ($status) {
            'FAIL' => 'critical',
            'WARN' => 'warning',
            default => 'info',
        };
    }
}
