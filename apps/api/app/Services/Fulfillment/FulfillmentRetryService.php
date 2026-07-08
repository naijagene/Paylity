<?php

namespace App\Services\Fulfillment;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Platform\SystemSettingsService;
use App\Services\TransactionEventService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Carbon;

class FulfillmentRetryService
{
    public function __construct(
        private readonly FulfillmentService $fulfillmentService,
        private readonly FulfillmentAttemptRecorder $fulfillmentAttemptRecorder,
        private readonly TransactionEventService $transactionEventService,
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    public function scheduleOrProcess(Transaction $transaction): bool
    {
        if ($transaction->needs_manual_review) {
            return false;
        }

        if (! in_array($transaction->status, [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FAILED,
            TransactionStatus::FULFILLMENT_PENDING,
        ], true)) {
            return false;
        }

        if ($transaction->status === TransactionStatus::PAYMENT_SUCCESS
            && ! $this->shouldRetryPaymentSuccess($transaction)) {
            return false;
        }

        if ($transaction->status === TransactionStatus::FULFILLMENT_PENDING) {
            $transaction->update(['status' => TransactionStatus::PAYMENT_SUCCESS]);
            $transaction = $transaction->fresh();
        }

        if ($this->isDue($transaction)) {
            return $this->processRetry($transaction);
        }

        if ($transaction->next_fulfillment_retry_at === null) {
            $this->scheduleNextRetry($transaction, 'Initial fulfillment retry scheduled.');

            return true;
        }

        return false;
    }

    /**
     * @return array{processed: int, succeeded: int, scheduled: int, escalated: int, errors: int}
     */
    public function processDueRetries(): array
    {
        $summary = [
            'processed' => 0,
            'succeeded' => 0,
            'scheduled' => 0,
            'escalated' => 0,
            'errors' => 0,
        ];

        Transaction::query()
            ->where('needs_manual_review', false)
            ->whereIn('status', [
                TransactionStatus::PAYMENT_SUCCESS,
                TransactionStatus::FAILED,
            ])
            ->where(function ($query) {
                $query
                    ->where('next_fulfillment_retry_at', '<=', now())
                    ->orWhereNull('next_fulfillment_retry_at');
            })
            ->orderBy('id')
            ->chunkById(50, function ($transactions) use (&$summary) {
                foreach ($transactions as $transaction) {
                    if (! $this->isDue($transaction)) {
                        continue;
                    }

                    $summary['processed']++;
                    $outcome = $this->processRetry($transaction);

                    if ($outcome === 'succeeded') {
                        $summary['succeeded']++;
                    } elseif ($outcome === 'scheduled') {
                        $summary['scheduled']++;
                    } elseif ($outcome === 'escalated') {
                        $summary['escalated']++;
                    } else {
                        $summary['errors']++;
                    }
                }
            });

        return $summary;
    }

    private function isDue(Transaction $transaction): bool
    {
        if ($transaction->next_fulfillment_retry_at === null) {
            return $transaction->status === TransactionStatus::FAILED
                || $transaction->status === TransactionStatus::PAYMENT_SUCCESS;
        }

        return $transaction->next_fulfillment_retry_at->lte(now());
    }

    /**
     * @return 'succeeded'|'scheduled'|'escalated'|'skipped'
     */
    private function processRetry(Transaction $transaction): string
    {
        if ($transaction->fulfillment_retry_count >= $this->maxAttempts()) {
            $this->escalateToManualReview(
                $transaction,
                'Maximum automated fulfillment retries exhausted.',
            );

            return 'escalated';
        }

        try {
            $fulfilled = $this->fulfillmentService->retryFulfillment($transaction, 'retry_engine');
            $fulfilled->update([
                'fulfillment_retry_count' => 0,
                'next_fulfillment_retry_at' => null,
                'needs_manual_review' => false,
                'manual_review_reason' => null,
                'manual_review_at' => null,
            ]);

            return 'succeeded';
        } catch (\Throwable $exception) {
            $fresh = $transaction->fresh();
            $attemptNumber = (int) $fresh->fulfillment_retry_count + 1;

            if ($attemptNumber >= $this->maxAttempts()) {
                $this->escalateToManualReview(
                    $fresh,
                    $fresh->failure_reason ?: $exception->getMessage(),
                );

                return 'escalated';
            }

            $this->scheduleNextRetry(
                $fresh,
                'Automated fulfillment retry failed; next attempt scheduled.',
                $attemptNumber,
                $fresh->failure_reason ?: $exception->getMessage(),
            );

            return 'scheduled';
        }
    }

    public function scheduleAfterFailure(Transaction $transaction, string $reason): void
    {
        if ($transaction->needs_manual_review) {
            return;
        }

        if ($transaction->fulfillment_retry_count >= $this->maxAttempts()) {
            $this->escalateToManualReview($transaction, $reason);

            return;
        }

        $this->scheduleNextRetry(
            $transaction,
            'Fulfillment failure scheduled for automated retry.',
            null,
            $reason,
        );
    }

    private function shouldRetryPaymentSuccess(Transaction $transaction): bool
    {
        return $transaction->fulfillment_retry_count > 0
            || $transaction->fulfillmentAttempts()->exists()
            || (bool) data_get($transaction->response_payload, 'auto_fulfill.attempted');
    }

    private function scheduleNextRetry(
        Transaction $transaction,
        string $summary,
        ?int $attemptNumber = null,
        ?string $failureReason = null,
    ): void {
        $attemptNumber ??= (int) $transaction->fulfillment_retry_count + 1;
        $intervalMinutes = $this->intervalForAttempt($attemptNumber);

        $transaction->update([
            'fulfillment_retry_count' => $attemptNumber,
            'next_fulfillment_retry_at' => now()->addMinutes($intervalMinutes),
            'failure_reason' => $failureReason ?? $transaction->failure_reason,
        ]);

        $this->transactionEventService->record(
            $transaction->fresh(),
            TransactionEventService::TYPE_FULFILLMENT_RETRY_SCHEDULED,
            $summary,
            'retry_engine',
            [
                'attempt_number' => $attemptNumber,
                'retry_in_minutes' => $intervalMinutes,
            ],
        );
    }

    private function escalateToManualReview(Transaction $transaction, string $reason): void
    {
        $fresh = $transaction->fresh();

        $fresh->update([
            'needs_manual_review' => true,
            'manual_review_reason' => $reason,
            'manual_review_at' => now(),
            'next_fulfillment_retry_at' => null,
        ]);

        $this->fulfillmentAttemptRecorder->record(
            $fresh,
            'dead_letter',
            'retry_engine',
            null,
            null,
            null,
            $reason,
        );

        $this->transactionEventService->record(
            $fresh,
            TransactionEventService::TYPE_FULFILLMENT_ESCALATED,
            'Transaction escalated to manual review after retry exhaustion.',
            'retry_engine',
            ['reason' => $reason],
        );

        $this->transactionEventService->record(
            $fresh,
            TransactionEventService::TYPE_MANUAL_REVIEW_REQUIRED,
            $reason,
            'retry_engine',
        );
    }

    public function maxAttempts(): int
    {
        return max(1, $this->systemSettings->getInt(
            SystemSettingKeys::FULFILLMENT_RETRY_MAX_ATTEMPTS,
            3,
        ));
    }

    /**
     * @return list<int>
     */
    public function retryIntervalsMinutes(): array
    {
        $raw = $this->systemSettings->getString(
            SystemSettingKeys::FULFILLMENT_RETRY_INTERVALS_MINUTES,
            '5,15,60',
        );

        $intervals = array_values(array_filter(array_map(
            fn (string $value): int => max(1, (int) trim($value)),
            explode(',', $raw),
        )));

        return $intervals !== [] ? $intervals : [5, 15, 60];
    }

    private function intervalForAttempt(int $attemptNumber): int
    {
        $intervals = $this->retryIntervalsMinutes();

        return $intervals[min($attemptNumber - 1, count($intervals) - 1)];
    }

    /**
     * @return array<string, int>
     */
    public function queueCounts(): array
    {
        return [
            'due_now' => (int) Transaction::query()
                ->where('needs_manual_review', false)
                ->whereIn('status', [TransactionStatus::PAYMENT_SUCCESS, TransactionStatus::FAILED])
                ->where(function ($query) {
                    $query
                        ->where('next_fulfillment_retry_at', '<=', Carbon::now())
                        ->orWhereNull('next_fulfillment_retry_at');
                })
                ->count(),
            'scheduled' => (int) Transaction::query()
                ->where('needs_manual_review', false)
                ->whereNotNull('next_fulfillment_retry_at')
                ->where('next_fulfillment_retry_at', '>', Carbon::now())
                ->count(),
            'manual_review' => (int) Transaction::query()
                ->where('needs_manual_review', true)
                ->count(),
        ];
    }
}
