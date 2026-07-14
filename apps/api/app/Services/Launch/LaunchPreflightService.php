<?php

namespace App\Services\Launch;

use App\Models\LedgerAccount;
use App\Models\ProviderService;
use App\Models\ProviderVariation;
use App\Enums\LedgerAccountCode;
use App\Models\TransactionFinancial;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Platform\SystemSettingsService;
use App\Support\CorsOriginResolver;
use App\Support\Platform\PaylityEnvironmentValidator;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LaunchPreflightService
{
    public const STATUS_READY = 'READY';

    public const STATUS_READY_WITH_WARNINGS = 'READY_WITH_WARNINGS';

    public const STATUS_BLOCKED = 'BLOCKED';

    /** @var list<string> */
    private const WEAK_OPERATOR_KEYS = [
        'dev-ops-key-123',
        'test-operator-key',
        'changeme',
        'operator',
        'secret',
    ];

    public function __construct(
        private readonly PaylityEnvironmentValidator $environmentValidator,
        private readonly PaystackModeInspector $paystackModeInspector,
        private readonly VtpassModeInspector $vtpassModeInspector,
        private readonly SchedulerHeartbeatService $schedulerHeartbeatService,
        private readonly PricingAuditService $pricingAuditService,
        private readonly DatabaseFingerprintService $databaseFingerprintService,
        private readonly FinancialLedgerService $financialLedgerService,
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
        $checks = [];

        foreach ($this->environmentValidator->validate() as $result) {
            $checks[] = $this->normalizeCheck('application', $result['check'], $result['status'], $result['detail']);
        }

        $checks = array_merge($checks, $this->databaseChecks());
        $checks = array_merge($checks, $this->paystackChecks($environment, $strict));
        $checks = array_merge($checks, $this->vtpassChecks($environment, $strict));
        $checks = array_merge($checks, $this->operationsChecks($strict));
        $checks = array_merge($checks, $this->financeChecks($strict));
        $checks = array_merge($checks, $this->catalogChecks());
        $checks = array_merge($checks, $this->backupChecks($strict));
        $checks = array_merge($checks, $this->pricingChecks($strict));

        if ($reference) {
            $checks[] = $this->referenceCheck($reference);
        }

        $status = $this->resolveStatus($checks, $strict);
        $summary = $this->summarize($checks);

        $this->settings->set(SystemSettingKeys::PREFLIGHT_LAST_RUN_AT, now()->toIso8601String());
        $this->settings->set(SystemSettingKeys::PREFLIGHT_LAST_STATUS, $status);

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
    private function databaseChecks(): array
    {
        $fingerprint = $this->databaseFingerprintService->fingerprint();
        $checks = [
            $this->normalizeCheck(
                'database',
                'connectivity',
                $fingerprint['writable'] ? 'PASS' : 'FAIL',
                $fingerprint['writable'] ? 'Database connection is writable.' : 'Database is not writable or unreachable.',
            ),
            $this->normalizeCheck(
                'database',
                'migration_status',
                ($fingerprint['migration_status']['pending'] ?? 1) === 0 ? 'PASS' : 'FAIL',
                sprintf(
                    'Migrations ran: %d, pending: %d',
                    $fingerprint['migration_status']['ran'] ?? 0,
                    $fingerprint['migration_status']['pending'] ?? 0,
                ),
            ),
        ];

        foreach (['transactions', 'ledger_accounts', 'ledger_transactions', 'system_settings', 'feature_flags'] as $table) {
            $checks[] = $this->normalizeCheck(
                'database',
                "table_{$table}",
                Schema::hasTable($table) ? 'PASS' : 'FAIL',
                Schema::hasTable($table) ? "Table {$table} exists." : "Required table {$table} is missing.",
            );
        }

        if ($fingerprint['driver'] === 'sqlite' && ($fingerprint['transaction_count'] ?? 0) === 0) {
            $checks[] = $this->normalizeCheck(
                'database',
                'sqlite_non_empty',
                'WARN',
                'SQLite database has zero transactions. Confirm this is intentional before launch.',
            );
        }

        return $checks;
    }

    /**
     * @return list<array<string, string>>
     */
    private function paystackChecks(string $environment, bool $strict): array
    {
        $mode = $this->paystackModeInspector->inspect();
        $callback = (string) ($mode['callback_url'] ?? '');
        $checks = [
            $this->normalizeCheck(
                'paystack',
                'enabled',
                ($mode['enabled'] ?? false) ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                ($mode['enabled'] ?? false) ? 'Paystack is enabled.' : 'Paystack is disabled.',
            ),
            $this->normalizeCheck(
                'paystack',
                'configuration_complete',
                ($mode['configuration_complete'] ?? false) ? 'PASS' : 'FAIL',
                ($mode['configuration_complete'] ?? false) ? 'Paystack configuration is complete.' : 'Paystack keys or callback URL are missing.',
            ),
            $this->normalizeCheck(
                'paystack',
                'mode',
                $environment === 'production' && ($mode['mode'] ?? 'unknown') === 'test'
                    ? ($strict ? 'FAIL' : 'WARN')
                    : 'PASS',
                'Detected Paystack mode: '.($mode['mode'] ?? 'unknown'),
            ),
            $this->normalizeCheck(
                'paystack',
                'callback_url',
                $callback !== '' ? 'PASS' : 'FAIL',
                $callback !== '' ? "Callback URL configured: {$callback}" : 'PAYSTACK_CALLBACK_URL is missing.',
            ),
            $this->normalizeCheck(
                'paystack',
                'webhook_route',
                ($mode['webhook_route_exists'] ?? false) ? 'PASS' : 'FAIL',
                '/api/v1/payments/paystack/webhook route must exist.',
            ),
        ];

        if ($environment === 'production' && Str::contains($callback, ['localhost', 'staging.', '127.0.0.1'])) {
            $checks[] = $this->normalizeCheck(
                'paystack',
                'production_callback',
                $strict ? 'FAIL' : 'WARN',
                'Production callback URL appears to reference staging or localhost.',
            );
        }

        return $checks;
    }

    /**
     * @return list<array<string, string>>
     */
    private function vtpassChecks(string $environment, bool $strict): array
    {
        $mode = $this->vtpassModeInspector->inspect();
        $checks = [
            $this->normalizeCheck(
                'vtpass',
                'enabled',
                ($mode['enabled'] ?? false) ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                ($mode['enabled'] ?? false) ? 'VTPass is enabled.' : 'VTPass is disabled.',
            ),
            $this->normalizeCheck(
                'vtpass',
                'configuration_complete',
                ($mode['configuration_complete'] ?? false) ? 'PASS' : 'FAIL',
                ($mode['configuration_complete'] ?? false) ? 'VTPass credentials are configured.' : 'VTPass credentials are incomplete.',
            ),
            $this->normalizeCheck(
                'vtpass',
                'mode',
                $environment === 'production' && ($mode['mode'] ?? 'sandbox') === 'sandbox'
                    ? ($strict ? 'FAIL' : 'WARN')
                    : 'PASS',
                'Detected VTPass mode: '.($mode['mode'] ?? 'unknown').' ('.($mode['host'] ?? '').')',
            ),
            $this->normalizeCheck(
                'vtpass',
                'sandbox_tests',
                ($mode['sandbox_tests_disabled'] ?? true) ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                ($mode['sandbox_tests_disabled'] ?? true) ? 'VTPASS_SANDBOX_TESTS is disabled.' : 'VTPASS_SANDBOX_TESTS=true should not be used in production.',
            ),
        ];

        return $checks;
    }

    /**
     * @return list<array<string, string>>
     */
    private function operationsChecks(bool $strict): array
    {
        $operatorKey = (string) config('services.operator.access_key');
        $origins = CorsOriginResolver::allowedOrigins();
        $scheduler = $this->schedulerHeartbeatService->snapshot();

        $checks = [
            $this->normalizeCheck(
                'operations',
                'operator_key_configured',
                $operatorKey !== '' ? 'PASS' : 'FAIL',
                $operatorKey !== '' ? 'Operator access key is configured.' : 'OPERATOR_ACCESS_KEY is missing.',
            ),
            $this->normalizeCheck(
                'operations',
                'operator_key_strength',
                $this->isWeakOperatorKey($operatorKey) ? ($strict ? 'FAIL' : 'WARN') : 'PASS',
                $this->isWeakOperatorKey($operatorKey)
                    ? 'Operator key matches a known development/default value.'
                    : 'Operator key does not match known weak defaults.',
            ),
            $this->normalizeCheck(
                'operations',
                'cors_origins',
                $origins !== [] ? 'PASS' : 'WARN',
                $origins !== [] ? 'CORS origins configured.' : 'No explicit CORS origins configured.',
            ),
            $this->normalizeCheck(
                'operations',
                'scheduler_heartbeat',
                match ($scheduler['status']) {
                    SchedulerHeartbeatService::STATUS_HEALTHY => 'PASS',
                    SchedulerHeartbeatService::STATUS_WARNING => 'WARN',
                    default => $strict ? 'FAIL' : 'WARN',
                },
                'Scheduler heartbeat status: '.($scheduler['status'] ?? 'unknown'),
            ),
        ];

        if (in_array('*', $origins, true)) {
            $checks[] = $this->normalizeCheck('operations', 'cors_wildcard', $strict ? 'FAIL' : 'WARN', 'Wildcard CORS origin is not allowed for production.');
        }

        return $checks;
    }

    /**
     * @return list<array<string, string>>
     */
    private function financeChecks(bool $strict): array
    {
        $ledgerAccounts = Schema::hasTable('ledger_accounts') ? LedgerAccount::query()->count() : 0;
        $negativeMargins = Schema::hasTable('transaction_financials')
            ? TransactionFinancial::query()->where('gross_margin_kobo', '<', 0)->count()
            : 0;

        $clearing = $this->financialLedgerService->accountBalance(LedgerAccountCode::PAYSTACK_CLEARING);
        $difference = $this->financialLedgerService->accountBalance(LedgerAccountCode::SETTLEMENT_DIFFERENCE);

        return [
            $this->normalizeCheck(
                'finance',
                'ledger_accounts_seeded',
                $ledgerAccounts > 0 ? 'PASS' : 'FAIL',
                $ledgerAccounts > 0 ? "Ledger accounts present ({$ledgerAccounts})." : 'Ledger accounts are not seeded.',
            ),
            $this->normalizeCheck(
                'finance',
                'negative_margin_count',
                $negativeMargins === 0 ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                "Transactions with negative gross margin: {$negativeMargins}",
            ),
            $this->normalizeCheck(
                'finance',
                'settlement_difference',
                abs((int) ($difference['balance_kobo'] ?? 0)) === 0 ? 'PASS' : 'WARN',
                'Settlement difference balance kobo: '.((int) ($difference['balance_kobo'] ?? 0)),
            ),
            $this->normalizeCheck(
                'finance',
                'paystack_clearing',
                'PASS',
                'Paystack clearing balance kobo: '.((int) ($clearing['balance_kobo'] ?? 0)),
            ),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function catalogChecks(): array
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

        return [
            $this->normalizeCheck('catalog', 'airtime_networks', $airtimeNetworks > 0 ? 'PASS' : 'FAIL', "Active airtime networks: {$airtimeNetworks}"),
            $this->normalizeCheck('catalog', 'data_products', $dataProducts > 0 ? 'PASS' : 'FAIL', "Active data products: {$dataProducts}"),
            $this->normalizeCheck('catalog', 'electricity_providers', $electricityProviders > 0 ? 'PASS' : 'FAIL', "Active electricity providers: {$electricityProviders}"),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function backupChecks(bool $strict): array
    {
        $lastRun = $this->settings->getString(SystemSettingKeys::BACKUP_LAST_RUN_AT);
        $verifiedAt = $this->settings->getString(SystemSettingKeys::BACKUP_LAST_VERIFIED_AT);
        $ageHours = $lastRun !== '' ? now()->diffInHours(\Carbon\Carbon::parse($lastRun)) : null;

        return [
            $this->normalizeCheck(
                'backup',
                'latest_backup',
                $lastRun !== '' && ($ageHours !== null && $ageHours <= 24) ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                $lastRun !== '' ? "Latest backup at {$lastRun}" : 'No database backup recorded.',
            ),
            $this->normalizeCheck(
                'backup',
                'verified_backup',
                $verifiedAt !== '' ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                $verifiedAt !== '' ? "Latest verified backup at {$verifiedAt}" : 'No verified backup on record.',
            ),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function pricingChecks(bool $strict): array
    {
        $audit = $this->pricingAuditService->audit();

        return [
            $this->normalizeCheck(
                'pricing',
                'launch_amounts_positive_margin',
                ($audit['all_positive'] ?? false) ? 'PASS' : ($strict ? 'FAIL' : 'WARN'),
                'Negative margin launch amounts: '.($audit['negative_margin_count'] ?? 0),
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function referenceCheck(string $reference): array
    {
        $exists = Schema::hasTable('transactions')
            && DB::table('transactions')->where('reference', $reference)->exists();

        return $this->normalizeCheck(
            'smoke',
            'reference_exists',
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
     * @return array{category: string, check: string, status: string, detail: string}
     */
    private function normalizeCheck(string $category, string $check, string $status, string $detail): array
    {
        return [
            'category' => $category,
            'check' => $check,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    private function isWeakOperatorKey(string $key): bool
    {
        if ($key === '' || strlen($key) < 16) {
            return true;
        }

        return in_array(strtolower($key), self::WEAK_OPERATOR_KEYS, true);
    }
}
