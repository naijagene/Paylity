<?php

namespace App\Services\Finance;

use App\Enums\TransactionStatus;
use App\Models\DailyFinancialSnapshot;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use App\Models\TransactionFinancial;
use App\Services\Fulfillment\VtpassWalletBalanceService;
use App\Support\Finance\Money;
use Carbon\Carbon;

class FinancialCloseService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
        private readonly VtpassWalletBalanceService $walletBalanceService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function close(
        ?string $date = null,
        bool $dryRun = false,
        bool $repair = true,
        bool $force = false,
    ): array {
        $day = $date ? Carbon::parse($date)->startOfDay() : today()->subDay()->startOfDay();
        $end = $day->copy()->endOfDay();

        $existing = DailyFinancialSnapshot::query()
            ->where('snapshot_date', $day->toDateString())
            ->first();

        if ($existing && $existing->status === 'finalized' && ! $force) {
            return [
                'status' => 'finalized',
                'snapshot_date' => $day->toDateString(),
                'message' => 'Snapshot already finalized. Use --force to rebuild.',
                'metrics' => $existing->metrics,
            ];
        }

        $successfulStatuses = [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
        ];

        $txnQuery = Transaction::query()->whereBetween('created_at', [$day, $end]);

        $metrics = [
            'gross_collections_kobo' => Money::nairaToKobo((int) (clone $txnQuery)->whereIn('status', $successfulStatuses)->sum('payable_amount')),
            'product_value_kobo' => Money::nairaToKobo((int) (clone $txnQuery)->whereIn('status', $successfulStatuses)->sum('product_amount')),
            'convenience_fee_revenue_kobo' => Money::nairaToKobo((int) (clone $txnQuery)->where('status', TransactionStatus::FULFILLED)->sum('convenience_fee')),
            'gateway_fees_charged_kobo' => Money::nairaToKobo((int) (clone $txnQuery)->whereIn('status', $successfulStatuses)->sum('gateway_fee')),
            'provider_cost_kobo' => (int) TransactionFinancial::query()
                ->whereIn('transaction_id', (clone $txnQuery)->where('status', TransactionStatus::FULFILLED)->pluck('id'))
                ->sum('provider_cost_kobo'),
            'gross_margin_kobo' => (int) TransactionFinancial::query()
                ->whereIn('transaction_id', (clone $txnQuery)->where('status', TransactionStatus::FULFILLED)->pluck('id'))
                ->sum('gross_margin_kobo'),
            'fulfilled_count' => (int) (clone $txnQuery)->where('status', TransactionStatus::FULFILLED)->count(),
            'failed_payment_count' => (int) (clone $txnQuery)->where('status', TransactionStatus::PAYMENT_FAILED)->count(),
            'paid_unfulfilled_count' => (int) (clone $txnQuery)->where('status', TransactionStatus::PAYMENT_SUCCESS)->count(),
            'paystack_clearing_balance_kobo' => $this->ledger->accountBalance(\App\Enums\LedgerAccountCode::PAYSTACK_CLEARING)['balance_kobo'],
            'settlement_difference_kobo' => $this->ledger->accountBalance(\App\Enums\LedgerAccountCode::SETTLEMENT_DIFFERENCE)['balance_kobo'],
            'ledger_imbalance_count' => count($this->ledger->imbalanceTransactions()),
            'wallet' => $this->walletBalanceService->dailyStats($day->toDateString()),
        ];

        $metrics['paylity_revenue_kobo'] = $metrics['convenience_fee_revenue_kobo']
            + (int) LedgerEntry::query()
                ->whereHas('account', fn ($q) => $q->where('code', \App\Enums\LedgerAccountCode::PRODUCT_MARGIN_REVENUE))
                ->where('entry_type', 'credit')
                ->whereHas('ledgerTransaction', fn ($q) => $q->whereBetween('posted_at', [$day, $end]))
                ->sum('amount_kobo');

        $metrics['gateway_fee_expected_kobo'] = (int) TransactionFinancial::query()
            ->whereIn('transaction_id', (clone $txnQuery)->whereIn('status', $successfulStatuses)->pluck('id'))
            ->sum('gateway_fee_expected_kobo');

        $metrics['gateway_fee_actual_kobo'] = (int) TransactionFinancial::query()
            ->whereIn('transaction_id', (clone $txnQuery)->whereIn('status', $successfulStatuses)->pluck('id'))
            ->sum('gateway_fee_actual_kobo');

        $hasExceptions = $metrics['ledger_imbalance_count'] > 0
            || $metrics['paid_unfulfilled_count'] > 0
            || abs($metrics['settlement_difference_kobo']) > 0;

        $status = $hasExceptions ? 'finalized_with_exceptions' : 'finalized';

        if ($dryRun) {
            return [
                'status' => 'dry_run',
                'snapshot_date' => $day->toDateString(),
                'metrics' => $metrics,
                'would_finalize' => $repair,
            ];
        }

        if (! $repair) {
            return [
                'status' => 'preview',
                'snapshot_date' => $day->toDateString(),
                'metrics' => $metrics,
            ];
        }

        $snapshot = DailyFinancialSnapshot::query()->updateOrCreate(
            ['snapshot_date' => $day->toDateString()],
            [
                'metrics' => $metrics,
                'status' => $status,
                'finalized_at' => now(),
            ],
        );

        return [
            'status' => $status,
            'snapshot_date' => $day->toDateString(),
            'snapshot_id' => $snapshot->id,
            'metrics' => $metrics,
        ];
    }
}
