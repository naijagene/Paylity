<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerEventType;
use App\Enums\ProviderCostStatus;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Transaction;
use Database\Seeders\LedgerAccountSeeder;
use Illuminate\Support\Facades\DB;

class FinancialLedgerService
{
    /** @var array<string, int> */
    private array $accountCache = [];

    /**
     * @param  list<array{account: string, type: string, amount_kobo: int}>  $lines
     * @param  array<string, mixed>  $metadata
     */
    public function post(
        Transaction $transaction,
        string $eventType,
        string $description,
        array $lines,
        array $metadata = [],
        ?string $operatorId = null,
        ?string $sourceType = 'transaction',
        ?string $sourceId = null,
    ): LedgerTransaction {
        $idempotencyKey = $this->idempotencyKey($transaction->id, $eventType);

        $existing = LedgerTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $this->assertBalanced($lines);

        return DB::transaction(function () use ($transaction, $eventType, $description, $lines, $metadata, $operatorId, $sourceType, $sourceId, $idempotencyKey) {
            $ledgerTransaction = LedgerTransaction::query()->create([
                'transaction_id' => $transaction->id,
                'source_type' => $sourceType ?? 'transaction',
                'source_id' => $sourceId ?? (string) $transaction->id,
                'transaction_reference' => $transaction->reference,
                'event_type' => $eventType,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'status' => 'posted',
                'metadata' => $metadata,
                'operator_id' => $operatorId,
                'posted_at' => now(),
            ]);

            foreach ($lines as $line) {
                LedgerEntry::query()->create([
                    'ledger_transaction_id' => $ledgerTransaction->id,
                    'account_id' => $this->accountId($line['account']),
                    'entry_type' => $line['type'],
                    'amount_kobo' => $line['amount_kobo'],
                    'currency' => 'NGN',
                ]);
            }

            return $ledgerTransaction->load('entries.account');
        });
    }

    public function hasPosting(int $transactionId, string $eventType): bool
    {
        return LedgerTransaction::query()
            ->where('transaction_id', $transactionId)
            ->where('event_type', $eventType)
            ->exists();
    }

    public function idempotencyKey(int $transactionId, string $eventType): string
    {
        return sprintf('txn:%d:%s', $transactionId, $eventType);
    }

    /**
     * @param  list<array{account: string, type: string, amount_kobo: int}>  $lines
     */
    private function assertBalanced(array $lines): void
    {
        $debits = 0;
        $credits = 0;

        foreach ($lines as $line) {
            if ($line['amount_kobo'] <= 0) {
                throw new \InvalidArgumentException('Ledger line amounts must be positive.');
            }

            if ($line['type'] === 'debit') {
                $debits += $line['amount_kobo'];
            } elseif ($line['type'] === 'credit') {
                $credits += $line['amount_kobo'];
            } else {
                throw new \InvalidArgumentException('Ledger line type must be debit or credit.');
            }
        }

        if ($debits !== $credits) {
            throw new \InvalidArgumentException(sprintf(
                'Ledger transaction is unbalanced: debits=%d credits=%d',
                $debits,
                $credits,
            ));
        }
    }

    private function accountId(string $code): int
    {
        if (isset($this->accountCache[$code])) {
            return $this->accountCache[$code];
        }

        $account = LedgerAccount::query()->where('code', $code)->first();

        if (! $account) {
            app(LedgerAccountSeeder::class)->run();
            $account = LedgerAccount::query()->where('code', $code)->first();
        }

        if (! $account) {
            throw new \RuntimeException("Ledger account not found: {$code}");
        }

        $this->accountCache[$code] = $account->id;

        return $account->id;
    }

    /**
     * @return array{debits: int, credits: int, balance_kobo: int}
     */
    public function accountBalance(string $accountCode): array
    {
        $accountId = $this->accountId($accountCode);

        $debits = (int) LedgerEntry::query()
            ->where('account_id', $accountId)
            ->where('entry_type', 'debit')
            ->sum('amount_kobo');

        $credits = (int) LedgerEntry::query()
            ->where('account_id', $accountId)
            ->where('entry_type', 'credit')
            ->sum('amount_kobo');

        return [
            'debits' => $debits,
            'credits' => $credits,
            'balance_kobo' => $debits - $credits,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function imbalanceTransactions(): array
    {
        return LedgerTransaction::query()
            ->with('entries')
            ->get()
            ->filter(function (LedgerTransaction $txn) {
                $debits = $txn->entries->where('entry_type', 'debit')->sum('amount_kobo');
                $credits = $txn->entries->where('entry_type', 'credit')->sum('amount_kobo');

                return $debits !== $credits;
            })
            ->map(fn (LedgerTransaction $txn) => [
                'id' => $txn->id,
                'reference' => $txn->transaction_reference,
                'event_type' => $txn->event_type,
            ])
            ->values()
            ->all();
    }
}
