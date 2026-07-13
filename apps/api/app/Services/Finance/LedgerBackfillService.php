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
        $summary = [
            'transactions_inspected' => 0,
            'payment_postings_created' => 0,
            'fulfillment_postings_created' => 0,
            'skipped' => 0,
            'already_posted' => 0,
            'manual_review' => 0,
            'errors' => 0,
            'eligible_in_database' => $this->countEligibleInDatabase(),
            'candidates_selected' => 0,
            'verbose_details' => [],
        ];

        $transactions = $this->selectCandidates($reference, $since, $date, $limit);
        $summary['candidates_selected'] = $transactions->count();

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

    private function countEligibleInDatabase(): int
    {
        return Transaction::query()->whereIn('status', $this->eligibleStatuses())->count();
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
