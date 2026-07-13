<?php

namespace App\Services\Ops;

use App\Enums\LedgerAccountCode;
use App\Enums\TransactionStatus;
use App\Models\DailyFinancialSnapshot;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\SettlementBatch;
use App\Models\Transaction;
use App\Models\TransactionFinancial;
use App\Services\Finance\FinancialAlertService;
use App\Services\Finance\FinancialLedgerService;
use App\Support\Finance\Money;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OpsFinanceService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
        private readonly FinancialAlertService $financialAlertService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $todayFrom = today()->startOfDay();
        $todayTo = today()->endOfDay();

        $successfulStatuses = [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
        ];

        $todayQuery = Transaction::query()->whereBetween('created_at', [$todayFrom, $todayTo]);
        $fulfilledToday = (clone $todayQuery)->where('status', TransactionStatus::FULFILLED);

        $grossCollection = Money::nairaToKobo((int) (clone $todayQuery)->whereIn('status', $successfulStatuses)->sum('payable_amount'));
        $productValue = Money::nairaToKobo((int) (clone $todayQuery)->whereIn('status', $successfulStatuses)->sum('product_amount'));
        $convenienceFees = Money::nairaToKobo((int) $fulfilledToday->sum('convenience_fee'));
        $gatewayFees = Money::nairaToKobo((int) (clone $todayQuery)->whereIn('status', $successfulStatuses)->sum('gateway_fee'));

        $financialIds = $fulfilledToday->pluck('id');
        $providerCost = (int) TransactionFinancial::query()->whereIn('transaction_id', $financialIds)->sum('provider_cost_kobo');
        $grossMargin = (int) TransactionFinancial::query()->whereIn('transaction_id', $financialIds)->sum('gross_margin_kobo');

        $paylityRevenue = $convenienceFees + (int) LedgerEntry::query()
            ->whereHas('account', fn ($q) => $q->where('code', LedgerAccountCode::PRODUCT_MARGIN_REVENUE))
            ->where('entry_type', 'credit')
            ->whereHas('ledgerTransaction', fn ($q) => $q->whereBetween('posted_at', [$todayFrom, $todayTo]))
            ->sum('amount_kobo');

        return [
            'refreshed_at' => now()->toIso8601String(),
            'cards' => [
                'gross_collection_today_kobo' => $grossCollection,
                'paylity_revenue_today_kobo' => $paylityRevenue,
                'product_value_today_kobo' => $productValue,
                'provider_cost_today_kobo' => $providerCost,
                'gateway_fees_today_kobo' => $gatewayFees,
                'gross_margin_today_kobo' => $grossMargin,
                'paystack_clearing_kobo' => $this->ledger->accountBalance(LedgerAccountCode::PAYSTACK_CLEARING)['balance_kobo'],
                'settlement_difference_kobo' => $this->ledger->accountBalance(LedgerAccountCode::SETTLEMENT_DIFFERENCE)['balance_kobo'],
            ],
            'recent_postings' => $this->recentPostings(20),
            'daily_summaries' => $this->dailySummaries(14),
            'settlement_exceptions' => $this->settlementExceptions(20),
            'alerts' => $this->financialAlertService->scan()['alerts'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentPostings(int $limit = 20): array
    {
        return LedgerTransaction::query()
            ->with(['entries.account', 'transaction'])
            ->orderByDesc('posted_at')
            ->limit($limit)
            ->get()
            ->map(fn (LedgerTransaction $txn) => $this->mapPosting($txn))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dailySummaries(int $limit = 14): array
    {
        return DailyFinancialSnapshot::query()
            ->orderByDesc('snapshot_date')
            ->limit($limit)
            ->get()
            ->map(fn (DailyFinancialSnapshot $snapshot) => [
                'date' => $snapshot->snapshot_date?->toDateString(),
                'collections_kobo' => (int) data_get($snapshot->metrics, 'gross_collections_kobo', 0),
                'revenue_kobo' => (int) data_get($snapshot->metrics, 'paylity_revenue_kobo', 0),
                'provider_cost_kobo' => (int) data_get($snapshot->metrics, 'provider_cost_kobo', 0),
                'gateway_fee_kobo' => (int) data_get($snapshot->metrics, 'gateway_fees_charged_kobo', 0),
                'margin_kobo' => (int) data_get($snapshot->metrics, 'gross_margin_kobo', 0),
                'difference_kobo' => (int) data_get($snapshot->metrics, 'settlement_difference_kobo', 0),
                'close_status' => $snapshot->status,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function settlementExceptions(int $limit = 20): array
    {
        return SettlementBatch::query()
            ->with('items')
            ->whereIn('status', ['exceptions', 'open'])
            ->orderByDesc('settlement_date')
            ->limit($limit)
            ->get()
            ->map(fn (SettlementBatch $batch) => [
                'reference' => sprintf('%s-%s', $batch->provider, $batch->settlement_date?->toDateString()),
                'provider' => $batch->provider,
                'expected_kobo' => (int) $batch->expected_amount_kobo,
                'actual_kobo' => (int) ($batch->actual_amount_kobo ?? 0),
                'difference_kobo' => (int) $batch->difference_kobo,
                'age_days' => $batch->settlement_date ? now()->diffInDays($batch->settlement_date) : null,
                'status' => $batch->status,
                'exception_count' => $batch->items->where('status', 'exception')->count(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function transactionFinance(Transaction $transaction): array
    {
        $financial = TransactionFinancial::query()->where('transaction_id', $transaction->id)->first();

        return [
            'summary' => [
                'customer_paid_kobo' => Money::nairaToKobo((int) $transaction->payable_amount),
                'product_amount_kobo' => Money::nairaToKobo((int) $transaction->product_amount),
                'convenience_fee_kobo' => Money::nairaToKobo((int) $transaction->convenience_fee),
                'gateway_fee_charged_kobo' => Money::nairaToKobo((int) $transaction->gateway_fee),
                'gateway_fee' => [
                    'expected_kobo' => $financial?->gateway_fee_expected_kobo,
                    'actual_kobo' => $financial?->gateway_fee_actual_kobo,
                    'charged_kobo' => Money::nairaToKobo((int) $transaction->gateway_fee),
                ],
                'provider_cost_kobo' => $financial?->provider_cost_kobo,
                'provider_cost_source' => $financial?->provider_cost_source,
                'provider_cost_status' => $financial?->provider_cost_status,
                'gross_margin_kobo' => $financial?->gross_margin_kobo,
                'settlement_status' => $financial?->settlement_status ?? 'pending',
            ],
            'ledger_history' => LedgerTransaction::query()
                ->with('entries.account')
                ->where('transaction_id', $transaction->id)
                ->orderBy('posted_at')
                ->get()
                ->map(fn (LedgerTransaction $txn) => $this->mapPosting($txn))
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function ledgerEntries(array $filters): LengthAwarePaginator
    {
        $query = LedgerTransaction::query()
            ->with(['entries.account', 'transaction'])
            ->orderByDesc('posted_at');

        if (! empty($filters['reference'])) {
            $query->where('transaction_reference', 'like', '%'.$filters['reference'].'%');
        }

        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('posted_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('posted_at', '<=', $filters['date_to']);
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 50), 1), 200);

        return $query->paginate($perPage);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportDailySummary(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : today()->subDays(30)->startOfDay();
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : today()->endOfDay();

        return DailyFinancialSnapshot::query()
            ->whereBetween('snapshot_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn (DailyFinancialSnapshot $snapshot) => array_merge(
                ['date' => $snapshot->snapshot_date?->toDateString(), 'status' => $snapshot->status],
                (array) $snapshot->metrics,
            ))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPosting(LedgerTransaction $txn): array
    {
        $debit = $txn->entries->firstWhere('entry_type', 'debit');
        $credit = $txn->entries->firstWhere('entry_type', 'credit');

        return [
            'id' => $txn->id,
            'reference' => $txn->transaction_reference,
            'event_type' => $txn->event_type,
            'description' => $txn->description,
            'debit_account' => $debit?->account?->code,
            'credit_account' => $credit?->account?->code,
            'amount_kobo' => (int) ($debit?->amount_kobo ?? $credit?->amount_kobo ?? 0),
            'status' => $txn->status,
            'posted_at' => $txn->posted_at?->toIso8601String(),
            'reversed' => $txn->reversed_by_id !== null,
            'entries' => $txn->entries->map(fn (LedgerEntry $entry) => [
                'account' => $entry->account?->code,
                'type' => $entry->entry_type,
                'amount_kobo' => (int) $entry->amount_kobo,
            ])->all(),
        ];
    }
}
