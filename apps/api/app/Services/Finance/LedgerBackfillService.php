<?php

namespace App\Services\Finance;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Carbon\Carbon;

class LedgerBackfillService
{
    public function __construct(
        private readonly LedgerPostingService $ledgerPostingService,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function backfill(
        ?string $reference = null,
        ?string $since = null,
        int $limit = 50,
        bool $dryRun = false,
        bool $repair = true,
    ): array {
        $summary = [
            'transactions_inspected' => 0,
            'payment_postings_created' => 0,
            'fulfillment_postings_created' => 0,
            'skipped' => 0,
            'already_posted' => 0,
            'manual_review' => 0,
            'errors' => 0,
        ];

        $query = Transaction::query()->orderBy('id');

        if ($reference) {
            $query->where('reference', $reference);
        }

        if ($since) {
            $query->where('created_at', '>=', Carbon::parse($since));
        }

        $transactions = $query->limit(max(1, $limit))->get();

        foreach ($transactions as $transaction) {
            $summary['transactions_inspected']++;

            try {
                if ($transaction->needs_manual_review) {
                    $summary['manual_review']++;

                    if (! $this->shouldPostPartial($transaction)) {
                        $summary['skipped']++;

                        continue;
                    }
                }

                if (in_array($transaction->status, [
                    TransactionStatus::CREATED,
                    TransactionStatus::PAYMENT_PENDING,
                    TransactionStatus::PAYMENT_FAILED,
                    TransactionStatus::CANCELLED,
                ], true)) {
                    $summary['skipped']++;

                    continue;
                }

                if ($dryRun || ! $repair) {
                    if ($this->isPaymentCandidate($transaction)) {
                        $summary['payment_postings_created']++;
                    }

                    if ($transaction->status === TransactionStatus::FULFILLED) {
                        $summary['fulfillment_postings_created']++;
                    }

                    continue;
                }

                if ($this->isPaymentCandidate($transaction)) {
                    $before = $this->ledgerPostingService->postPaymentReceived($transaction);

                    if ($before) {
                        $summary['payment_postings_created']++;
                    } else {
                        $summary['already_posted']++;
                    }
                }

                if ($transaction->status === TransactionStatus::FULFILLED) {
                    $before = $this->ledgerPostingService->postFulfillmentRecognized($transaction->fresh());

                    if ($before) {
                        $summary['fulfillment_postings_created']++;
                    } else {
                        $summary['already_posted']++;
                    }
                }
            } catch (\Throwable) {
                $summary['errors']++;
            }
        }

        return $summary;
    }

    private function isPaymentCandidate(Transaction $transaction): bool
    {
        return in_array($transaction->status, [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
            TransactionStatus::FAILED,
        ], true);
    }

    private function shouldPostPartial(Transaction $transaction): bool
    {
        return $this->isPaymentCandidate($transaction) && ! $transaction->needs_manual_review;
    }
}
