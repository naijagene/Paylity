<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Models\SettlementBatch;
use App\Models\SettlementItem;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SettlementReconciliationService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
        private readonly GatewayFeeResolver $gatewayFeeResolver,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function reconcile(
        ?string $date = null,
        ?string $reference = null,
        int $limit = 50,
        bool $dryRun = false,
        bool $repair = true,
    ): array {
        $summary = [
            'inspected' => 0,
            'expected_created' => 0,
            'matched' => 0,
            'under_settlement' => 0,
            'over_settlement' => 0,
            'differences_recorded' => 0,
            'skipped' => 0,
            'already_posted' => 0,
            'errors' => 0,
        ];

        $day = $date ? Carbon::parse($date)->startOfDay() : today()->startOfDay();

        $query = Transaction::query()
            ->whereIn('status', [
                TransactionStatus::PAYMENT_SUCCESS,
                TransactionStatus::FULFILLMENT_PENDING,
                TransactionStatus::FULFILLED,
            ])
            ->orderBy('id');

        if ($reference) {
            $query->where('reference', $reference);
        } else {
            $query->whereDate('created_at', $day->toDateString());
        }

        $transactions = $query->limit(max(1, $limit))->get();
        $batch = $this->resolveBatch('paystack', $day, $dryRun);

        foreach ($transactions as $transaction) {
            $summary['inspected']++;

            try {
                if (! $this->ledger->hasPosting($transaction->id, LedgerEventType::PAYMENT_RECEIVED)) {
                    $summary['skipped']++;

                    continue;
                }

                $expected = Money::nairaToKobo((int) $transaction->payable_amount);
                $actual = $this->resolveActualSettlement($transaction);
                $difference = $actual !== null ? $actual - $expected : 0;

                if ($this->ledger->hasPosting($transaction->id, LedgerEventType::SETTLEMENT_RECEIVED)) {
                    $summary['already_posted']++;
                } elseif ($actual === null) {
                    $summary['skipped']++;

                    continue;
                }

                if ($difference === 0) {
                    $summary['matched']++;
                } elseif ($difference < 0) {
                    $summary['under_settlement']++;
                } else {
                    $summary['over_settlement']++;
                }

                if ($repair && ! $dryRun) {
                    $this->postSettlementReceived($transaction, $actual);
                    $this->upsertSettlementItem($batch, $transaction, $expected, $actual, $difference);
                    $summary['expected_created']++;

                    if ($difference !== 0 && ! $this->ledger->hasPosting($transaction->id, LedgerEventType::SETTLEMENT_DIFFERENCE_RECORDED)) {
                        $this->postSettlementDifference($transaction, abs($difference), $difference < 0 ? 'under' : 'over');
                        $summary['differences_recorded']++;
                    }
                } elseif ($repair) {
                    $summary['expected_created']++;
                    if ($difference !== 0) {
                        $summary['differences_recorded']++;
                    }
                }
            } catch (\Throwable) {
                $summary['errors']++;
            }
        }

        if ($batch && $repair && ! $dryRun) {
            $this->finalizeBatch($batch);
        }

        return $summary;
    }

    private function resolveActualSettlement(Transaction $transaction): ?int
    {
        $metadata = (array) data_get($transaction->response_payload, 'verify', []);

        if (isset($metadata['settlement_amount']) && is_numeric($metadata['settlement_amount'])) {
            return (int) $metadata['settlement_amount'];
        }

        if ($this->ledger->hasPosting($transaction->id, LedgerEventType::SETTLEMENT_RECEIVED)) {
            return (int) Money::nairaToKobo((int) $transaction->payable_amount);
        }

        return null;
    }

    private function postSettlementReceived(Transaction $transaction, int $actual): void
    {
        if ($this->ledger->hasPosting($transaction->id, LedgerEventType::SETTLEMENT_RECEIVED)) {
            return;
        }

        $this->ledger->post(
            $transaction,
            LedgerEventType::SETTLEMENT_RECEIVED,
            'Paystack settlement received.',
            [
                ['account' => LedgerAccountCode::CASH_ADJUSTMENT, 'type' => 'debit', 'amount_kobo' => $actual],
                ['account' => LedgerAccountCode::PAYSTACK_CLEARING, 'type' => 'credit', 'amount_kobo' => $actual],
            ],
            ['actual_kobo' => $actual],
        );
    }

    private function postSettlementDifference(Transaction $transaction, int $amount, string $direction): void
    {
        if ($direction === 'under') {
            $lines = [
                ['account' => LedgerAccountCode::SETTLEMENT_DIFFERENCE, 'type' => 'debit', 'amount_kobo' => $amount],
                ['account' => LedgerAccountCode::PAYSTACK_CLEARING, 'type' => 'credit', 'amount_kobo' => $amount],
            ];
        } else {
            $lines = [
                ['account' => LedgerAccountCode::PAYSTACK_CLEARING, 'type' => 'debit', 'amount_kobo' => $amount],
                ['account' => LedgerAccountCode::SETTLEMENT_DIFFERENCE, 'type' => 'credit', 'amount_kobo' => $amount],
            ];
        }

        $this->ledger->post(
            $transaction,
            LedgerEventType::SETTLEMENT_DIFFERENCE_RECORDED,
            'Settlement difference recorded.',
            $lines,
            ['difference_kobo' => $amount, 'direction' => $direction],
        );
    }

    private function resolveBatch(string $provider, Carbon $day, bool $dryRun): ?SettlementBatch
    {
        if ($dryRun) {
            return null;
        }

        return SettlementBatch::query()->firstOrCreate(
            [
                'provider' => $provider,
                'settlement_date' => $day->toDateString(),
            ],
            [
                'status' => 'open',
            ],
        );
    }

    private function upsertSettlementItem(
        ?SettlementBatch $batch,
        Transaction $transaction,
        int $expected,
        int $actual,
        int $difference,
    ): void {
        if (! $batch) {
            return;
        }

        SettlementItem::query()->updateOrCreate(
            [
                'settlement_batch_id' => $batch->id,
                'transaction_id' => $transaction->id,
            ],
            [
                'transaction_reference' => $transaction->reference,
                'expected_amount_kobo' => $expected,
                'actual_amount_kobo' => $actual,
                'difference_kobo' => $difference,
                'status' => $difference === 0 ? 'matched' : 'exception',
            ],
        );
    }

    private function finalizeBatch(SettlementBatch $batch): void
    {
        $items = $batch->items()->get();

        $batch->update([
            'expected_amount_kobo' => (int) $items->sum('expected_amount_kobo'),
            'actual_amount_kobo' => (int) $items->sum('actual_amount_kobo'),
            'difference_kobo' => (int) $items->sum('difference_kobo'),
            'status' => $items->contains(fn ($item) => $item->status === 'exception') ? 'exceptions' : 'matched',
            'finalized_at' => now(),
        ]);
    }
}
