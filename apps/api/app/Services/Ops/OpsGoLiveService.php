<?php

namespace App\Services\Ops;

use App\Enums\LedgerAccountCode;
use App\Models\TransactionFinancial;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Launch\DatabaseFingerprintService;
use App\Services\Launch\LaunchBlockersService;
use App\Services\Launch\LaunchChecklistService;
use App\Services\Launch\LaunchExportService;
use App\Services\Launch\LaunchModeService;
use App\Services\Launch\LaunchPreflightService;
use App\Services\Launch\LaunchTimelineService;
use App\Services\Launch\PaymentCertificationService;
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
        private readonly LaunchBlockersService $launchBlockersService,
        private readonly LaunchChecklistService $launchChecklistService,
        private readonly LaunchTimelineService $launchTimelineService,
        private readonly LaunchExportService $launchExportService,
        private readonly SchedulerHeartbeatService $schedulerHeartbeatService,
        private readonly DatabaseFingerprintService $databaseFingerprintService,
        private readonly PaystackModeInspector $paystackModeInspector,
        private readonly VtpassModeInspector $vtpassModeInspector,
        private readonly PaymentCertificationService $paymentCertificationService,
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
        $environment = (string) config('app.env');
        $preflightStatus = $this->settings->getString(SystemSettingKeys::PREFLIGHT_LAST_STATUS, 'UNKNOWN');
        $launchMode = $this->launchModeService->mode();
        $preflight = $this->storedPreflight();
        $checks = is_array($preflight['checks'] ?? null) ? $preflight['checks'] : [];
        $blockers = $this->launchBlockersService->fromChecks($checks, $environment);

        return [
            'refreshed_at' => now()->toIso8601String(),
            'launch_status' => [
                'status' => $preflightStatus,
                'environment' => $environment,
                'environment_badge' => $this->environmentBadge($environment, $launchMode),
                'version' => (string) config('app.version'),
                'build' => (string) config('app.build'),
                'last_preflight_at' => $this->settings->getString(SystemSettingKeys::PREFLIGHT_LAST_RUN_AT) ?: null,
                'scheduler' => $this->schedulerHeartbeatService->snapshot(),
                'backup' => [
                    'last_run_at' => $this->settings->getString(SystemSettingKeys::BACKUP_LAST_RUN_AT) ?: null,
                    'last_verified_at' => $this->settings->getString(SystemSettingKeys::BACKUP_LAST_VERIFIED_AT) ?: null,
                ],
            ],
            'preflight' => [
                'status' => $preflight['status'] ?? $preflightStatus,
                'summary' => $preflight['summary'] ?? ['pass' => 0, 'warn' => 0, 'fail' => 0],
                'checks' => $checks,
            ],
            'blockers' => $blockers,
            'checklist' => $this->launchChecklistService->snapshot(),
            'timeline' => $this->launchTimelineService->snapshot(),
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
            'database_fingerprint' => $this->databaseFingerprintService->fingerprint(),
            'pricing_audit_summary' => [
                'negative_margin_count' => TransactionFinancial::query()->where('gross_margin_kobo', '<', 0)->count(),
                'all_positive' => TransactionFinancial::query()->where('gross_margin_kobo', '<', 0)->doesntExist(),
            ],
            'payment_certification' => $this->paymentCertificationService->snapshot(),
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

    /**
     * @return array<string, mixed>
     */
    public function heartbeat(): array
    {
        return $this->schedulerHeartbeatService->snapshot();
    }

    /**
     * @return array<string, mixed>
     */
    public function checklist(): array
    {
        return $this->launchChecklistService->snapshot();
    }

    /**
     * @param  array<string, bool>  $updates
     * @return array<string, mixed>
     */
    public function updateChecklist(array $updates): array
    {
        return $this->launchChecklistService->update($updates);
    }

    /**
     * @return array<string, mixed>
     */
    public function setLaunchMode(string $mode): array
    {
        return $this->launchModeService->setMode($mode);
    }

    /**
     * @return array<string, mixed>
     */
    public function exportJson(?string $operator = null): array
    {
        return $this->launchExportService->build($operator);
    }

    /**
     * @return array{html: string, filename: string}
     */
    public function exportPdf(?string $operator = null): array
    {
        return $this->launchExportService->renderPdf($operator);
    }

    /**
     * @return array<string, mixed>
     */
    private function storedPreflight(): array
    {
        $raw = $this->settings->get(SystemSettingKeys::PREFLIGHT_LAST_CHECKS, '[]');
        $checks = [];

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $checks = is_array($decoded) ? $decoded : [];
        } elseif (is_array($raw)) {
            $checks = $raw;
        }

        if ($checks === []) {
            return $this->launchPreflightService->run(
                environment: (string) config('app.env'),
                strict: false,
            );
        }

        $status = $this->settings->getString(SystemSettingKeys::PREFLIGHT_LAST_STATUS, 'UNKNOWN');

        return [
            'status' => $status,
            'summary' => [
                'pass' => (int) collect($checks)->where('status', 'PASS')->count(),
                'warn' => (int) collect($checks)->where('status', 'WARN')->count(),
                'fail' => (int) collect($checks)->where('status', 'FAIL')->count(),
            ],
            'checks' => $checks,
        ];
    }

    /**
     * @return array{label: string, variant: string}
     */
    private function environmentBadge(string $appEnv, string $launchMode): array
    {
        if ($launchMode === LaunchModeService::MODE_MAINTENANCE) {
            return ['label' => 'Maintenance', 'variant' => 'failed'];
        }

        if ($launchMode === LaunchModeService::MODE_SOFT_LAUNCH) {
            return ['label' => 'Soft Launch', 'variant' => 'processing'];
        }

        if ($launchMode === LaunchModeService::MODE_LIVE || $appEnv === 'production') {
            return ['label' => 'Production', 'variant' => 'success'];
        }

        return ['label' => 'Staging', 'variant' => 'info'];
    }
}
