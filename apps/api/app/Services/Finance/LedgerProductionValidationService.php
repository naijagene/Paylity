<?php

namespace App\Services\Finance;

use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerProductionValidationService
{
    public function __construct(
        private readonly LedgerBackfillService $ledgerBackfillService,
        private readonly FinancialLedgerService $ledger,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function report(int $candidateLimit = 50, ?string $since = null, ?string $date = null): array
    {
        $connection = (string) config('database.default');
        $driver = Transaction::query()->getConnection()->getDriverName();
        $eligibleStatuses = $this->ledgerBackfillService->eligibleStatuses();
        $candidateQuery = $this->ledgerBackfillService->buildCandidateQuery($since, $date);
        $candidateIds = (clone $candidateQuery)
            ->limit(max(1, $candidateLimit))
            ->pluck('id')
            ->all();

        $statusBreakdown = Transaction::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->pluck('total', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $eligibleTransactions = Transaction::query()
            ->whereIn('status', $eligibleStatuses)
            ->orderBy('id')
            ->get();

        return [
            'step_1_status_breakdown' => [
                'sql' => 'SELECT status, COUNT(*) FROM transactions GROUP BY status',
                'rows' => collect($statusBreakdown)
                    ->map(fn (int $count, string $status) => ['status' => $status, 'count' => $count])
                    ->values()
                    ->all(),
            ],
            'step_2_payment_evidence_counts' => [
                'payment_reference_not_null' => $this->countPaymentReferenceNotNull(),
                'verify_status_success' => $this->countJsonStatusSuccess('verify.status'),
                'webhook_data_status_success' => $this->countJsonStatusSuccess('webhook.data.status'),
                'driver' => $driver,
            ],
            'step_3_fulfilled_transactions' => $this->fulfilledTransactionReport(),
            'step_4_candidate_query' => $this->explainCandidateQuery($candidateQuery, $connection),
            'step_5_eligible_exclusions' => $this->explainEligibleExclusions(
                $eligibleTransactions,
                $candidateIds,
            ),
            'step_6_root_cause' => $this->ledgerBackfillService->diagnoseForCandidateBatch(count($candidateIds)),
            'ledger_posting_count' => LedgerTransaction::query()->count(),
        ];
    }

    private function countPaymentReferenceNotNull(): int
    {
        return Transaction::query()
            ->whereNotNull('payment_reference')
            ->where('payment_reference', '!=', '')
            ->count();
    }

    private function countJsonStatusSuccess(string $path): int
    {
        $driver = Transaction::query()->getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $jsonPath = '$.' . str_replace('.', '.', $path);

            return Transaction::query()
                ->whereRaw(
                    'LOWER(JSON_UNQUOTE(JSON_EXTRACT(response_payload, ?))) = ?',
                    [$jsonPath, 'success'],
                )
                ->count();
        }

        if ($driver === 'sqlite') {
            $jsonPath = '$.' . $path;

            return Transaction::query()
                ->whereRaw('LOWER(json_extract(response_payload, ?)) = ?', [$jsonPath, 'success'])
                ->count();
        }

        return Transaction::query()
            ->whereNotNull('response_payload')
            ->get(['id', 'response_payload'])
            ->filter(fn (Transaction $transaction) => strtolower((string) data_get($transaction->response_payload, $path, '')) === 'success')
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fulfilledTransactionReport(): array
    {
        return Transaction::query()
            ->where('status', TransactionStatus::FULFILLED)
            ->with(['ledgerTransactions' => function ($query) {
                $query->whereIn('event_type', [
                    LedgerEventType::PAYMENT_RECEIVED,
                    LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED,
                ]);
            }])
            ->orderBy('id')
            ->get()
            ->map(function (Transaction $transaction) {
                $postings = $transaction->ledgerTransactions;

                return [
                    'reference' => $transaction->reference,
                    'status' => $transaction->status,
                    'payment_reference' => $transaction->payment_reference,
                    'ledger_payment_posting_exists' => $postings->contains(
                        'event_type',
                        LedgerEventType::PAYMENT_RECEIVED,
                    ),
                    'ledger_fulfillment_posting_exists' => $postings->contains(
                        'event_type',
                        LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED,
                    ),
                    'needs_manual_review' => (bool) $transaction->needs_manual_review,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function explainCandidateQuery(Builder $candidateQuery, string $connection): array
    {
        $transactionModel = new Transaction;

        return [
            'sql' => $candidateQuery->toSql(),
            'bindings' => $candidateQuery->getBindings(),
            'interpolated_sql' => $this->interpolateSql($candidateQuery),
            'model_inspection' => [
                'table' => $transactionModel->getTable(),
                'connection' => $connection,
                'global_scopes_enabled' => $this->modelHasGlobalScopes($transactionModel),
                'soft_deletes_enabled' => in_array(SoftDeletes::class, class_uses_recursive($transactionModel), true),
                'lifecycle_filters' => [
                    'eligible_statuses' => $this->ledgerBackfillService->eligibleStatuses(),
                    'missing_payment_received_posting' => 'whereDoesntHave(ledgerTransactions.event_type = payment_received)',
                    'missing_customer_funds_recognized_posting' => 'status = fulfilled AND whereDoesntHave(ledgerTransactions.event_type = customer_funds_recognized)',
                ],
                'joins' => 'none (uses whereDoesntHave subqueries on ledger_transactions)',
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Transaction>  $eligibleTransactions
     * @param  list<int>  $candidateIds
     * @return list<array<string, mixed>>
     */
    private function explainEligibleExclusions($eligibleTransactions, array $candidateIds): array
    {
        $candidateLookup = array_fill_keys($candidateIds, true);
        $exclusions = [];

        foreach ($eligibleTransactions as $transaction) {
            if (isset($candidateLookup[$transaction->id])) {
                continue;
            }

            $hasPaymentPosting = $this->ledger->hasPosting($transaction->id, LedgerEventType::PAYMENT_RECEIVED);
            $hasFulfillmentPosting = $this->ledger->hasPosting($transaction->id, LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED);

            if ($hasPaymentPosting && ($transaction->status !== TransactionStatus::FULFILLED || $hasFulfillmentPosting)) {
                $condition = 'has_payment_received_posting AND (status != fulfilled OR has_customer_funds_recognized_posting)';
                $reason = 'Candidate query only selects rows missing payment_received, or fulfilled rows missing customer_funds_recognized.';
            } else {
                $condition = 'unexpected_exclusion';
                $reason = 'Eligible row excluded without matching candidate-query predicate.';
            }

            $exclusions[] = [
                'reference' => $transaction->reference,
                'status' => $transaction->status,
                'condition' => $condition,
                'reason' => $reason,
                'ledger_payment_posting_exists' => $hasPaymentPosting,
                'ledger_fulfillment_posting_exists' => $hasFulfillmentPosting,
            ];
        }

        return $exclusions;
    }

    private function modelHasGlobalScopes(Transaction $model): bool
    {
        return (new \ReflectionClass($model))->getAttributes(\Illuminate\Database\Eloquent\Attributes\ScopedBy::class) !== [];
    }

    private function interpolateSql(Builder $query): string
    {
        $sql = $query->toSql();

        foreach ($query->getBindings() as $binding) {
            $value = is_numeric($binding) ? (string) $binding : "'" . str_replace("'", "''", (string) $binding) . "'";
            $sql = preg_replace('/\?/', $value, (string) $sql, 1);
        }

        return $sql;
    }
}
