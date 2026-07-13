<?php

namespace App\Services\Finance;

use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LedgerBackfillService
{
    public const SKIP_TERMINAL_UNPAID = 'terminal_unpaid_status';

    public const SKIP_NO_CONFIRMED_PAYMENT = 'no_confirmed_payment';

    public const SKIP_MANUAL_REVIEW = 'manual_review_uncertain';

    public const SKIP_ALREADY_POSTED = 'already_posted';

    public const ROOT_CAUSE_EMPTY_DATABASE = 'empty_database';

    public const ROOT_CAUSE_STATUS_MISMATCH = 'status_mismatch';

    public const ROOT_CAUSE_ALREADY_POSTED = 'already_posted';

    public const ROOT_CAUSE_NONE = 'none';

    public function __construct(
        private readonly LedgerPostingService $ledgerPostingService,
        private readonly FinancialLedgerService $ledger,
    ) {
    }

    /**
     * @return array<string, int|list<string>>
     */
    public function backfill(
        ?string $reference = null,
        ?string $since = null,
        ?string $date = null,
        int $limit = 50,
        bool $dryRun = false,
        bool $repair = true,
        bool $verbose = false,
    ): array {
        $diagnostics = $this->diagnose();

        $summary = [
            'transactions_inspected' => 0,
            'payment_postings_created' => 0,
            'fulfillment_postings_created' => 0,
            'skipped' => 0,
            'already_posted' => 0,
            'manual_review' => 0,
            'errors' => 0,
            'eligible_in_database' => $diagnostics['eligible_in_database'],
            'candidates_selected' => 0,
            'verbose_details' => [],
            'diagnostics' => $diagnostics,
        ];

        $transactions = $this->selectCandidates($reference, $since, $date, $limit);
        $summary['candidates_selected'] = $transactions->count();
        $summary['diagnostics'] = $this->finalizeDiagnostics($diagnostics, (int) $summary['candidates_selected']);

        foreach ($transactions as $transaction) {
            $summary['transactions_inspected']++;

            try {
                $plan = $this->classify($transaction);

                if ($plan['skip_reason'] !== null) {
                    if ($plan['skip_reason'] === self::SKIP_MANUAL_REVIEW) {
                        $summary['manual_review']++;
                    }

                    $summary['skipped']++;
                    $this->appendVerbose($summary, $verbose, $transaction->reference, $plan['skip_reason'], $transaction->status);

                    continue;
                }

                $needsPayment = $plan['post_payment'] && ! $this->ledger->hasPosting($transaction->id, LedgerEventType::PAYMENT_RECEIVED);
                $needsFulfillment = $plan['post_fulfillment']
                    && ! $this->ledger->hasPosting($transaction->id, LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED);

                if (! $needsPayment && ! $needsFulfillment) {
                    $summary['already_posted']++;
                    $this->appendVerbose($summary, $verbose, $transaction->reference, self::SKIP_ALREADY_POSTED, $transaction->status);

                    continue;
                }

                if ($dryRun || ! $repair) {
                    if ($needsPayment) {
                        $summary['payment_postings_created']++;
                    }

                    if ($needsFulfillment) {
                        $summary['fulfillment_postings_created']++;
                    }

                    $this->appendVerbose($summary, $verbose, $transaction->reference, 'would_post', $transaction->status, [
                        'payment' => $needsPayment,
                        'fulfillment' => $needsFulfillment,
                    ]);

                    continue;
                }

                if ($needsPayment) {
                    $posted = $this->ledgerPostingService->postPaymentReceived($transaction);

                    if ($posted) {
                        $summary['payment_postings_created']++;
                    } else {
                        $summary['already_posted']++;
                    }
                }

                if ($needsFulfillment) {
                    $posted = $this->ledgerPostingService->postFulfillmentRecognized($transaction->fresh());

                    if ($posted) {
                        $summary['fulfillment_postings_created']++;
                    } else {
                        $summary['already_posted']++;
                    }
                }
            } catch (\Throwable) {
                $summary['errors']++;
                $this->appendVerbose($summary, $verbose, $transaction->reference, 'error', $transaction->status);
            }
        }

        if (! $verbose) {
            unset($summary['verbose_details']);
        }

        return $summary;
    }

    /**
     * @return array{
     *     database_connection: string,
     *     database_name: string|null,
     *     total_transactions_in_database: int,
     *     eligible_in_database: int,
     *     eligible_statuses: list<string>,
     *     distinct_status_values: list<string>,
     *     status_breakdown: array<string, int>,
     *     paid_but_ineligible_status_count: int,
     *     root_cause: string,
     *     root_cause_detail: string
     * }
     */
    public function diagnose(): array
    {
        $connection = (string) config('database.default');
        $eligibleStatuses = $this->eligibleStatuses();
        $totalTransactions = Transaction::query()->count();
        $statusBreakdown = Transaction::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->pluck('total', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $eligibleInDatabase = Transaction::query()
            ->whereIn('status', $eligibleStatuses)
            ->count();

        $paidButIneligible = Transaction::query()
            ->whereNotIn('status', $eligibleStatuses)
            ->where(function (Builder $builder) {
                $builder
                    ->where(function (Builder $referenceQuery) {
                        $referenceQuery
                            ->whereNotNull('payment_reference')
                            ->where('payment_reference', '!=', '');
                    })
                    ->orWhereNotNull('fulfilled_at');
            })
            ->count();

        $rootCause = $this->resolveRootCause(
            $connection,
            $totalTransactions,
            $eligibleInDatabase,
            $statusBreakdown,
            $eligibleStatuses,
            $paidButIneligible,
            null,
        );

        return [
            'database_connection' => $connection,
            'database_name' => $this->resolveDatabaseName($connection),
            'total_transactions_in_database' => $totalTransactions,
            'eligible_in_database' => $eligibleInDatabase,
            'eligible_statuses' => $eligibleStatuses,
            'distinct_status_values' => array_keys($statusBreakdown),
            'status_breakdown' => $statusBreakdown,
            'paid_but_ineligible_status_count' => $paidButIneligible,
            'root_cause' => $rootCause['code'],
            'root_cause_detail' => $rootCause['detail'],
        ];
    }

    /**
     * @param  array<string, int>  $statusBreakdown
     * @param  list<string>  $eligibleStatuses
     * @return array{code: string, detail: string}
     */
    private function resolveRootCause(
        string $connection,
        int $totalTransactions,
        int $eligibleInDatabase,
        array $statusBreakdown,
        array $eligibleStatuses,
        int $paidButIneligible,
        ?int $candidatesSelected,
    ): array {
        if ($totalTransactions === 0) {
            $databaseName = $this->resolveDatabaseName($connection);

            return [
                'code' => self::ROOT_CAUSE_EMPTY_DATABASE,
                'detail' => sprintf(
                    'Transaction table contains 0 rows on connection "%s"%s. Artisan is not reading the same database as the Ops API. Laravel defaults DB_CONNECTION to sqlite when .env is missing or not loaded from apps/api.',
                    $connection,
                    $databaseName ? " (database: {$databaseName})" : '',
                ),
            ];
        }

        if ($eligibleInDatabase === 0) {
            $breakdown = collect($statusBreakdown)
                ->map(fn (int $count, string $status) => "{$status}={$count}")
                ->implode(', ');

            $detail = sprintf(
                '%d transactions exist but 0 match backfill-eligible statuses (%s). Distinct statuses in database: %s.',
                $totalTransactions,
                implode(', ', $eligibleStatuses),
                $breakdown !== '' ? $breakdown : 'none',
            );

            if ($paidButIneligible > 0) {
                $detail .= sprintf(
                    ' %d row(s) show payment or fulfillment evidence while still outside eligible statuses (lifecycle mismatch).',
                    $paidButIneligible,
                );
            }

            return [
                'code' => self::ROOT_CAUSE_STATUS_MISMATCH,
                'detail' => $detail,
            ];
        }

        if ($candidatesSelected === 0) {
            return [
                'code' => self::ROOT_CAUSE_ALREADY_POSTED,
                'detail' => sprintf(
                    '%d eligible transactions exist but none are missing required ledger postings for this batch.',
                    $eligibleInDatabase,
                ),
            ];
        }

        return [
            'code' => self::ROOT_CAUSE_NONE,
            'detail' => 'Eligible transactions are present and candidates were selected for inspection.',
        ];
    }

    /**
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    private function finalizeDiagnostics(array $diagnostics, int $candidatesSelected): array
    {
        $rootCause = $this->resolveRootCause(
            (string) $diagnostics['database_connection'],
            (int) $diagnostics['total_transactions_in_database'],
            (int) $diagnostics['eligible_in_database'],
            (array) $diagnostics['status_breakdown'],
            (array) $diagnostics['eligible_statuses'],
            (int) $diagnostics['paid_but_ineligible_status_count'],
            $candidatesSelected,
        );

        $diagnostics['root_cause'] = $rootCause['code'];
        $diagnostics['root_cause_detail'] = $rootCause['detail'];

        return $diagnostics;
    }

    private function resolveDatabaseName(string $connection): ?string
    {
        $database = config("database.connections.{$connection}.database");

        return is_string($database) && $database !== '' ? $database : null;
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function selectCandidates(?string $reference, ?string $since, ?string $date, int $limit): Collection
    {
        if ($reference !== null && $reference !== '') {
            $transaction = Transaction::query()->where('reference', $reference)->first();

            return $transaction ? collect([$transaction]) : collect();
        }

        return $this->candidateQuery($since, $date)
            ->limit(max(1, $limit))
            ->get();
    }

    private function candidateQuery(?string $since, ?string $date): Builder
    {
        $query = Transaction::query()
            ->whereIn('status', $this->eligibleStatuses())
            ->where(function (Builder $builder) {
                $builder
                    ->whereDoesntHave('ledgerTransactions', function (Builder $ledgerQuery) {
                        $ledgerQuery->where('event_type', LedgerEventType::PAYMENT_RECEIVED);
                    })
                    ->orWhere(function (Builder $fulfilledQuery) {
                        $fulfilledQuery
                            ->where('status', TransactionStatus::FULFILLED)
                            ->whereDoesntHave('ledgerTransactions', function (Builder $ledgerQuery) {
                                $ledgerQuery->where('event_type', LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED);
                            });
                    });
            })
            ->orderBy('id');

        if ($since !== null && $since !== '') {
            $query->where('created_at', '>=', Carbon::parse($since)->startOfDay());
        }

        if ($date !== null && $date !== '') {
            $query->whereDate('created_at', Carbon::parse($date)->toDateString());
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private function eligibleStatuses(): array
    {
        return [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
            TransactionStatus::FAILED,
        ];
    }

    /**
     * @return array{
     *     skip_reason: string|null,
     *     post_payment: bool,
     *     post_fulfillment: bool
     * }
     */
    private function classify(Transaction $transaction): array
    {
        if (in_array($transaction->status, [
            TransactionStatus::CREATED,
            TransactionStatus::VALIDATED,
            TransactionStatus::PAYMENT_PENDING,
            TransactionStatus::PAYMENT_FAILED,
            TransactionStatus::CANCELLED,
        ], true)) {
            return [
                'skip_reason' => self::SKIP_TERMINAL_UNPAID,
                'post_payment' => false,
                'post_fulfillment' => false,
            ];
        }

        if (! $this->hasConfirmedPayment($transaction)) {
            return [
                'skip_reason' => self::SKIP_NO_CONFIRMED_PAYMENT,
                'post_payment' => false,
                'post_fulfillment' => false,
            ];
        }

        $postPayment = in_array($transaction->status, [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
            TransactionStatus::FAILED,
        ], true);

        $postFulfillment = $transaction->status === TransactionStatus::FULFILLED;

        if ($transaction->needs_manual_review) {
            if ($postPayment) {
                return [
                    'skip_reason' => null,
                    'post_payment' => true,
                    'post_fulfillment' => false,
                ];
            }

            return [
                'skip_reason' => self::SKIP_MANUAL_REVIEW,
                'post_payment' => false,
                'post_fulfillment' => false,
            ];
        }

        return [
            'skip_reason' => null,
            'post_payment' => $postPayment,
            'post_fulfillment' => $postFulfillment,
        ];
    }

    private function hasConfirmedPayment(Transaction $transaction): bool
    {
        if (! in_array($transaction->status, [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
            TransactionStatus::FAILED,
        ], true)) {
            return false;
        }

        if ($this->ledger->hasPosting($transaction->id, LedgerEventType::PAYMENT_RECEIVED)) {
            return true;
        }

        if (filled($transaction->payment_reference)) {
            return true;
        }

        $payload = (array) ($transaction->response_payload ?? []);

        if (strtolower((string) data_get($payload, 'verify.status', '')) === 'success') {
            return true;
        }

        return strtolower((string) data_get($payload, 'webhook.data.status', '')) === 'success';
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $extra
     */
    private function appendVerbose(
        array &$summary,
        bool $verbose,
        string $reference,
        string $reason,
        string $status,
        array $extra = [],
    ): void {
        if (! $verbose) {
            return;
        }

        $summary['verbose_details'][] = array_merge([
            'reference' => $reference,
            'status' => $status,
            'reason' => $reason,
        ], $extra);
    }
}
