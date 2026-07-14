<?php

namespace App\Services\Launch;

use App\Enums\LedgerAccountCode;
use App\Models\TransactionFinancial;
use App\Services\Finance\FinancialLedgerService;
use App\Services\Fulfillment\VtpassWalletBalanceService;
use App\Services\Ops\OpsReliabilityService;

class LaunchExportService
{
    public function __construct(
        private readonly LaunchPreflightService $launchPreflightService,
        private readonly LaunchBlockersService $launchBlockersService,
        private readonly LaunchChecklistService $launchChecklistService,
        private readonly LaunchTimelineService $launchTimelineService,
        private readonly LaunchModeService $launchModeService,
        private readonly PaystackModeInspector $paystackModeInspector,
        private readonly VtpassModeInspector $vtpassModeInspector,
        private readonly FinancialLedgerService $financialLedgerService,
        private readonly VtpassWalletBalanceService $walletBalanceService,
        private readonly OpsReliabilityService $opsReliabilityService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(?string $operator = null): array
    {
        $environment = (string) config('app.env');
        $preflight = $this->launchPreflightService->run(environment: $environment, strict: false);
        $checks = is_array($preflight['checks'] ?? null) ? $preflight['checks'] : [];
        $blockers = $this->launchBlockersService->fromChecks($checks, $environment);
        $wallet = $this->walletBalanceService->snapshot();
        $reliability = $this->opsReliabilityService->snapshot();

        return [
            'generated_at' => now()->toIso8601String(),
            'operator' => $operator,
            'build_version' => [
                'version' => (string) config('app.version'),
                'build' => (string) config('app.build'),
            ],
            'environment' => [
                'app_env' => $environment,
                'launch_mode' => $this->launchModeService->mode(),
                'app_debug' => (bool) config('app.debug'),
                'app_url' => (string) config('app.url'),
            ],
            'preflight' => [
                'status' => $preflight['status'] ?? 'UNKNOWN',
                'summary' => $preflight['summary'] ?? [],
                'checks' => $checks,
            ],
            'blockers' => $blockers,
            'checklist' => $this->launchChecklistService->snapshot(),
            'timeline' => $this->launchTimelineService->snapshot(),
            'provider_modes' => [
                'paystack' => $this->paystackModeInspector->inspect(),
                'vtpass' => $this->vtpassModeInspector->inspect(),
            ],
            'finance_summary' => [
                'negative_margin_count' => TransactionFinancial::query()->where('gross_margin_kobo', '<', 0)->count(),
                'paystack_clearing_kobo' => (int) $this->financialLedgerService->accountBalance(LedgerAccountCode::PAYSTACK_CLEARING)['balance_kobo'],
                'settlement_difference_kobo' => (int) $this->financialLedgerService->accountBalance(LedgerAccountCode::SETTLEMENT_DIFFERENCE)['balance_kobo'],
            ],
            'ledger_summary' => [
                'paystack_clearing_kobo' => (int) $this->financialLedgerService->accountBalance(LedgerAccountCode::PAYSTACK_CLEARING)['balance_kobo'],
                'settlement_difference_kobo' => (int) $this->financialLedgerService->accountBalance(LedgerAccountCode::SETTLEMENT_DIFFERENCE)['balance_kobo'],
                'imbalance_count' => count($this->financialLedgerService->imbalanceTransactions()),
            ],
            'wallet' => $wallet,
            'reconciliation' => $reliability['reconciliation'] ?? null,
            'launch_mode' => $this->launchModeService->snapshot(),
        ];
    }

    /**
     * @return array{html: string, filename: string}
     */
    public function renderPdf(?string $operator = null): array
    {
        $report = $this->build($operator);

        $html = view('launch.report', ['report' => $report])->render();

        return [
            'html' => $html,
            'filename' => 'paylity-launch-report-'.now()->format('Ymd_His').'.html',
        ];
    }
}
