<?php

namespace App\Services\Fulfillment;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class AutoFulfillmentRecorder
{
    public const SKIP_FEATURE_FLAG_OFF = 'Auto-fulfillment disabled (FEATURE_VTPASS_AUTO_FULFILL=false).';

    public const SKIP_VTPASS_DISABLED = 'Auto-fulfillment skipped (FEATURE_VTPASS=false).';

    public const SKIP_NOT_PAYMENT_SUCCESS = 'Auto-fulfillment skipped (transaction not payment_success).';

    public function recordSkip(Transaction $transaction, string $reason): Transaction
    {
        $this->persist($transaction, [
            'attempted' => false,
            'outcome' => 'skipped',
            'reason' => $reason,
        ]);

        Log::info('Auto-fulfillment skipped after payment verification.', [
            'reference' => $transaction->reference,
            'reason' => $reason,
        ]);

        return $transaction->fresh();
    }

    public function recordAttempt(Transaction $transaction): Transaction
    {
        $this->persist($transaction, [
            'attempted' => true,
            'outcome' => 'attempted',
            'reason' => null,
        ]);

        Log::info('Auto-fulfillment attempted after payment verification.', [
            'reference' => $transaction->reference,
        ]);

        return $transaction->fresh();
    }

    public function recordSuccess(Transaction $transaction): Transaction
    {
        $this->persist($transaction, [
            'attempted' => true,
            'outcome' => 'success',
            'reason' => null,
        ]);

        Log::info('Auto-fulfillment succeeded after payment verification.', [
            'reference' => $transaction->reference,
            'status' => $transaction->status,
        ]);

        return $transaction->fresh();
    }

    public function recordFailure(Transaction $transaction, string $reason): Transaction
    {
        $this->persist($transaction, [
            'attempted' => true,
            'outcome' => 'failed',
            'reason' => $reason,
        ]);

        Log::warning('Auto-fulfillment failed after payment verification.', [
            'reference' => $transaction->reference,
            'reason' => $reason,
        ]);

        return $transaction->fresh();
    }

    /**
     * @param  array<string, mixed>|null  $responsePayload
     * @return array<string, mixed>
     */
    public static function summaryFromResponsePayload(?array $responsePayload): array
    {
        $autoFulfill = data_get($responsePayload, 'auto_fulfill');

        if (! is_array($autoFulfill)) {
            return [
                'auto_fulfill_attempted' => null,
                'auto_fulfill_outcome' => null,
                'auto_fulfill_reason' => null,
            ];
        }

        return [
            'auto_fulfill_attempted' => data_get($autoFulfill, 'attempted'),
            'auto_fulfill_outcome' => (string) data_get($autoFulfill, 'outcome', ''),
            'auto_fulfill_reason' => data_get($autoFulfill, 'reason'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(Transaction $transaction, array $data): void
    {
        $payload = (array) $transaction->response_payload;

        $payload['auto_fulfill'] = array_merge(
            (array) ($payload['auto_fulfill'] ?? []),
            $data,
            ['recorded_at' => now()->toIso8601String()],
        );

        $transaction->update(['response_payload' => $payload]);
    }
}
