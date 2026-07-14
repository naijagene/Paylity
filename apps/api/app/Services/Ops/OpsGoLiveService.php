<?php

namespace App\Services\Ops;

use App\Enums\LedgerAccountCode;
use App\Models\TransactionFinancial;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Launch\DatabaseFingerprintService;
use App\Services\Launch\LaunchModeService;
use App\Services\Launch\LaunchPreflightService;
use App\Services\Launch\PaystackModeInspector;
use App\Services\Launch\PricingAuditService;
use App\Services\Launch\SchedulerHeartbeatService;
use App\Services\Launch\VtpassModeInspector;
use App\Services\Platform\SystemSettingsService;
use App\Support\CorsOriginResolver;
use App\Support\Platform\SystemSettingKeys;

class OpsGoLiveService
{
    public function __construct(
        private readonly LaunchPreflightService $launchPreflightService,
        private readonly LaunchModeService $launchModeService,
        private readonly SchedulerHeartbeatService $schedulerHeartbeatService,
        private readonly DatabaseFingerprintService $databaseFingerprintService,
        private readonly PaystackModeInspector $paystackModeInspector,
        private readonly VtpassModeInspector $vtpassModeInspector,
        private readonly PricingAuditService $pricingAuditService,
        private readonly OpsMonitoringService $opsMonitoringService,
        private readonly FinancialLedgerService $financialLedgerService,
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $monitoring = $this->opsMonitoringService->summary();
        $preflightStatus = $this->settings->getString(SystemSettingKeys::PREFLIGHT_LAST_STATUS, 'UNKNOWN');
        $pricingAudit = $this->pricingAuditService->audit();

        return [
            'refreshed_at' => now()->toIso8601String(),
            'launch_status' => [
                'status' => $preflightStatus,
                'environment' => (string) config('app.env'),
                'version' => (string) config('app.version'),
                'build' => (string) config('app.build'),
                'last_preflight_at' => $this->settings->getString(SystemSettingKeys::PREFLIGHT_LAST_RUN_AT) ?: null,
                'scheduler' => $this->schedulerHeartbeatService->snapshot(),
                'backup' => [
                    'last_run_at' => $this->settings->getString(SystemSettingKeys::BACKUP_LAST_RUN_AT) ?: null,
                    'last_verified_at' => $this->settings->getString(SystemSettingKeys::BACKUP_LAST_VERIFIED_AT) ?: null,
                ],
            ],
            'launch_mode' => $this->launchModeService->snapshot(),
            'provider_mode' => [
                'paystack' => $this->paystackModeInspector->inspect(),
                'vtpass' => $this->vtpassModeInspector->inspect(),
                'wallet' => $monitoring['wallet'] ?? null,
            ],
            'security' => [
                'app_debug' => (bool) config('app.debug'),
                'https_app_url' => str_starts_with((string) config('app.url'), 'https://'),
                'cors_origins' => CorsOriginResolver::allowedOrigins(),
            ],
            'finance' => [
                'negative_margin_count' => TransactionFinancial::query()->where('gross_margin_kobo', '<', 0)->count(),
                'paystack_clearing_kobo' => (int) $this->financialLedgerService->accountBalance(LedgerAccountCode::PAYSTACK_CLEARING)['balance_kobo'],
                'settlement_difference_kobo' => (int) $this->financialLedgerService->accountBalance(LedgerAccountCode::SETTLEMENT_DIFFERENCE)['balance_kobo'],
            ],
            'operations' => [
                'queue' => $monitoring['queue'] ?? null,
                'wallet' => $monitoring['wallet'] ?? null,
                'vtpass' => $monitoring['vtpass'] ?? null,
            ],
            'catalog' => [],
            'database_fingerprint' => $this->databaseFingerprintService->fingerprint(),
            'pricing_audit_summary' => [
                'negative_margin_count' => $pricingAudit['negative_margin_count'] ?? 0,
                'all_positive' => $pricingAudit['all_positive'] ?? false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runPreflight(bool $strict = false, ?string $reference = null): array
    {
        return $this->launchPreflightService->run(
            environment: (string) config('app.env'),
            strict: $strict,
            reference: $reference,
        );
    }
}
