<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\DailyFinancialSnapshot;
use App\Models\Transaction;
use App\Models\TransactionFinancial;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Carbon\Carbon;

class FinancialAlertService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    /**
     * @return array{
     *     alerts: list<array<string, mixed>>,
     *     totals: array{alerts_detected: int, critical: int, warning: int},
     *     dry_run: bool
     * }
     */
    public function scan(bool $dryRun = false): array
    {
        $alerts = [];

        $imbalances = $this->ledger->imbalanceTransactions();

        if ($imbalances !== []) {
            $alerts[] = [
                'code' => 'LEDGER_IMBALANCE',
                'severity' => 'critical',
                'message' => count($imbalances).' ledger transaction(s) are unbalanced.',
                'count' => count($imbalances),
            ];
        }

        $missingPaymentPostings = Transaction::query()
            ->whereIn('status', [
                TransactionStatus::PAYMENT_SUCCESS,
                TransactionStatus::FULFILLMENT_PENDING,
                TransactionStatus::FULFILLED,
            ])
            ->whereDoesntHave('ledgerTransactions', fn ($q) => $q->where('event_type', LedgerEventType::PAYMENT_RECEIVED))
            ->count();

        if ($missingPaymentPostings > 0) {
            $alerts[] = [
                'code' => 'MISSING_PAYMENT_POSTING',
                'severity' => 'warning',
                'message' => "{$missingPaymentPostings} successful payment(s) lack ledger postings.",
                'count' => $missingPaymentPostings,
            ];
        }

        $missingProviderCost = Transaction::query()
            ->where('status', TransactionStatus::FULFILLED)
            ->whereDoesntHave('financial', fn ($q) => $q->whereNotNull('provider_cost_kobo'))
            ->count();

        if ($missingProviderCost > 0) {
            $alerts[] = [
                'code' => 'MISSING_PROVIDER_COST',
                'severity' => 'warning',
                'message' => "{$missingProviderCost} fulfilled transaction(s) lack provider cost.",
                'count' => $missingProviderCost,
            ];
        }

        if ($this->systemSettings->getBool(SystemSettingKeys::FINANCIAL_NEGATIVE_MARGIN_ALERT_ENABLED, true)) {
            $negativeMargins = TransactionFinancial::query()
                ->where('gross_margin_kobo', '<', 0)
                ->count();

            if ($negativeMargins > 0) {
                $alerts[] = [
                    'code' => 'NEGATIVE_MARGIN',
                    'severity' => 'warning',
                    'message' => "{$negativeMargins} transaction(s) have negative gross margin.",
                    'count' => $negativeMargins,
                ];
            }
        }

        $threshold = $this->systemSettings->getInt(
            SystemSettingKeys::FINANCIAL_SETTLEMENT_DIFFERENCE_THRESHOLD,
            50000,
        );

        $settlementDiff = abs($this->ledger->accountBalance(LedgerAccountCode::SETTLEMENT_DIFFERENCE)['balance_kobo']);

        if ($settlementDiff > $threshold) {
            $alerts[] = [
                'code' => 'SETTLEMENT_DIFFERENCE_THRESHOLD',
                'severity' => 'warning',
                'message' => 'Settlement difference exceeds configured threshold.',
                'amount_kobo' => $settlementDiff,
                'threshold_kobo' => $threshold,
            ];
        }

        $staleHours = $this->systemSettings->getInt(SystemSettingKeys::FINANCIAL_CLEARING_STALE_HOURS, 48);
        $clearingBalance = $this->ledger->accountBalance(LedgerAccountCode::PAYSTACK_CLEARING)['balance_kobo'];

        if ($clearingBalance > 0) {
            $oldest = Transaction::query()
                ->whereIn('status', [
                    TransactionStatus::PAYMENT_SUCCESS,
                    TransactionStatus::FULFILLMENT_PENDING,
                    TransactionStatus::FULFILLED,
                ])
                ->orderBy('created_at')
                ->value('created_at');

            if ($oldest && Carbon::parse($oldest)->lt(now()->subHours($staleHours))) {
                $alerts[] = [
                    'code' => 'STALE_PAYSTACK_CLEARING',
                    'severity' => 'warning',
                    'message' => 'Paystack clearing balance has been non-zero beyond stale threshold.',
                    'balance_kobo' => $clearingBalance,
                    'stale_hours' => $staleHours,
                ];
            }
        }

        $yesterday = today()->subDay()->toDateString();
        $closeSnapshot = DailyFinancialSnapshot::query()
            ->where('snapshot_date', $yesterday)
            ->first();

        if (! $closeSnapshot) {
            $alerts[] = [
                'code' => 'DAILY_CLOSE_NOT_COMPLETED',
                'severity' => 'warning',
                'message' => "Daily financial close not completed for {$yesterday}.",
                'date' => $yesterday,
            ];
        } elseif (str_contains((string) $closeSnapshot->status, 'exception')) {
            $alerts[] = [
                'code' => 'DAILY_CLOSE_WITH_EXCEPTIONS',
                'severity' => 'warning',
                'message' => "Daily close for {$yesterday} completed with exceptions.",
                'date' => $yesterday,
            ];
        }

        return [
            'alerts' => $alerts,
            'totals' => [
                'alerts_detected' => count($alerts),
                'critical' => count(array_filter($alerts, fn (array $alert) => ($alert['severity'] ?? '') === 'critical')),
                'warning' => count(array_filter($alerts, fn (array $alert) => ($alert['severity'] ?? '') === 'warning')),
            ],
            'dry_run' => $dryRun,
        ];
    }
}
