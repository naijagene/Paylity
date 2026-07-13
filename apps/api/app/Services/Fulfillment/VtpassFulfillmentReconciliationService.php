<?php

namespace App\Services\Fulfillment;

use App\Enums\FulfillmentAttemptStatus;
use App\Enums\TransactionStatus;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;
use App\Services\Platform\SystemSettingsService;
use App\Services\TransactionEventService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class VtpassFulfillmentReconciliationService
{
    public function __construct(
        private readonly VTPassService $vtpassService,
        private readonly VTPassResponseMapper $responseMapper,
        private readonly FulfillmentAttemptRecorder $attemptRecorder,
        private readonly FulfillmentRetryService $fulfillmentRetryService,
        private readonly TransactionEventService $transactionEventService,
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    /**
     * @return array{
     *     attempts_checked: int,
     *     attempts_repaired: int,
     *     retries_scheduled: int,
     *     escalated: int,
     *     errors: int
     * }
     */
    public function reconcile(
        ?string $reference = null,
        ?Carbon $since = null,
        int $limit = 50,
        bool $dryRun = false,
        bool $repair = true,
    ): array {
        $summary = [
            'attempts_checked' => 0,
            'attempts_repaired' => 0,
            'retries_scheduled' => 0,
            'escalated' => 0,
            'errors' => 0,
        ];

        $query = FulfillmentAttempt::query()
            ->with('transaction')
            ->whereIn('status', [
                FulfillmentAttemptStatus::UNCERTAIN,
                FulfillmentAttemptStatus::SUBMITTED,
                FulfillmentAttemptStatus::CONFIRMED_FAILED,
            ])
            ->orderBy('id');

        if ($reference !== null && $reference !== '') {
            $query->whereHas('transaction', fn ($builder) => $builder->where('reference', $reference));
        }

        if ($since !== null) {
            $query->where('started_at', '>=', $since);
        }

        $maxAgeDays = max(1, $this->systemSettings->getInt(
            SystemSettingKeys::RECONCILIATION_MAX_AGE_DAYS,
            30,
        ));
        $query->where('started_at', '>=', now()->subDays($maxAgeDays));

        $query->limit($limit)->get()->each(function (FulfillmentAttempt $attempt) use (
            &$summary,
            $dryRun,
            $repair,
        ) {
            $summary['attempts_checked']++;
            $this->reconcileAttempt($attempt, $summary, $dryRun, $repair);
        });

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function reconcileAttempt(
        FulfillmentAttempt $attempt,
        array &$summary,
        bool $dryRun,
        bool $repair,
    ): void {
        $transaction = $attempt->transaction;

        if (! $transaction || ! $attempt->request_id) {
            return;
        }

        $this->transactionEventService->record(
            $transaction,
            TransactionEventService::TYPE_FULFILLMENT_RECONCILIATION_STARTED,
            'Fulfillment reconciliation started.',
            'reconciliation',
            ['request_id' => $attempt->request_id],
        );

        if (! $this->vtpassService->isEnabled()) {
            return;
        }

        try {
            $response = $this->vtpassService->queryTransaction($attempt->request_id);
            $mapped = $this->responseMapper->map($response);

            if ($mapped['status'] === VTPassResponseMapper::STATUS_SUCCESS) {
                if ($dryRun || ! $repair) {
                    return;
                }

                $this->repairProviderSuccess($transaction, $attempt, $response, $mapped);
                $summary['attempts_repaired']++;

                return;
            }

            if ($mapped['status'] === VTPassResponseMapper::STATUS_FAILED) {
                if ($dryRun || ! $repair) {
                    return;
                }

                $this->attemptRecorder->markConfirmedFailed(
                    $attempt,
                    $response,
                    $mapped['code'],
                    $mapped['message'],
                    $mapped['message'],
                );

                if ($transaction->status !== TransactionStatus::FULFILLED) {
                    $transaction->update([
                        'status' => TransactionStatus::FAILED,
                        'failure_reason' => $mapped['message'],
                    ]);
                    $this->fulfillmentRetryService->scheduleAfterFailure($transaction->fresh(), $mapped['message']);
                    $summary['retries_scheduled']++;
                }

                return;
            }

            if ($this->isEscalationDue($attempt)) {
                if ($dryRun || ! $repair) {
                    return;
                }

                $this->escalateUncertainty($transaction, $attempt, $mapped['message']);
                $summary['escalated']++;
            }
        } catch (\Throwable $exception) {
            $summary['errors']++;
            Log::warning('Fulfillment reconciliation failed.', [
                'reference' => $transaction->reference,
                'request_id' => $attempt->request_id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $mapped
     */
    private function repairProviderSuccess(
        Transaction $transaction,
        FulfillmentAttempt $attempt,
        array $response,
        array $mapped,
    ): void {
        if ($transaction->status === TransactionStatus::FULFILLED) {
            $this->attemptRecorder->markSucceeded(
                $attempt,
                $response,
                (string) data_get($response, 'content.transactions.transactionId', $attempt->request_id),
                $mapped['code'],
                $mapped['message'],
            );

            return;
        }

        $providerReference = (string) (
            data_get($response, 'content.transactions.transactionId')
            ?? data_get($response, 'requestId')
            ?? $attempt->request_id
        );

        $transaction->update([
            'status' => TransactionStatus::FULFILLED,
            'fulfillment_provider' => 'vtpass',
            'fulfillment_reference' => $providerReference,
            'response_payload' => array_merge(
                (array) $transaction->response_payload,
                ['fulfillment' => $response],
            ),
            'failure_reason' => null,
            'fulfilled_at' => now(),
            'needs_manual_review' => false,
            'manual_review_reason' => null,
            'manual_review_at' => null,
            'next_fulfillment_retry_at' => null,
            'fulfillment_retry_count' => 0,
        ]);

        $this->attemptRecorder->markSucceeded(
            $attempt,
            $response,
            $providerReference,
            $mapped['code'],
            $mapped['message'],
        );

        $this->transactionEventService->record(
            $transaction->fresh(),
            TransactionEventService::TYPE_FULFILLMENT_RECONCILIATION_REPAIRED,
            'Provider success reconciled locally.',
            'reconciliation',
            ['request_id' => $attempt->request_id],
        );

        $this->transactionEventService->record(
            $transaction->fresh(),
            TransactionEventService::TYPE_FULFILLED,
            'Transaction fulfilled via reconciliation.',
            'reconciliation',
        );
    }

    private function escalateUncertainty(
        Transaction $transaction,
        FulfillmentAttempt $attempt,
        string $reason,
    ): void {
        $transaction->update([
            'needs_manual_review' => true,
            'manual_review_reason' => 'Prolonged uncertain provider outcome: '.$reason,
            'manual_review_at' => now(),
            'next_fulfillment_retry_at' => null,
        ]);

        $this->transactionEventService->record(
            $transaction->fresh(),
            TransactionEventService::TYPE_MANUAL_REVIEW_OPENED,
            'Uncertain fulfillment escalated to manual review.',
            'reconciliation',
            ['request_id' => $attempt->request_id],
        );
    }

    private function isEscalationDue(FulfillmentAttempt $attempt): bool
    {
        $minutes = max(
            15,
            $this->systemSettings->getInt(
                SystemSettingKeys::FULFILLMENT_UNCERTAIN_ESCALATION_MINUTES,
                120,
            ),
        );

        $startedAt = $attempt->submitted_at ?? $attempt->started_at ?? $attempt->attempted_at;

        return $startedAt !== null && $startedAt->lte(now()->subMinutes($minutes));
    }

    /**
     * @return array<string, int>
     */
    public function staleCounts(): array
    {
        $processingStale = max(5, $this->systemSettings->getInt(
            SystemSettingKeys::FULFILLMENT_PROCESSING_STALE_MINUTES,
            30,
        ));
        $uncertainEscalation = max(15, $this->systemSettings->getInt(
            SystemSettingKeys::FULFILLMENT_UNCERTAIN_ESCALATION_MINUTES,
            120,
        ));

        $processingCutoff = now()->subMinutes($processingStale);
        $uncertainCutoff = now()->subMinutes($uncertainEscalation);

        return [
            'uncertain_attempts' => (int) FulfillmentAttempt::query()
                ->whereIn('status', [FulfillmentAttemptStatus::UNCERTAIN, FulfillmentAttemptStatus::SUBMITTED])
                ->count(),
            'stale_processing' => (int) FulfillmentAttempt::query()
                ->whereIn('status', [FulfillmentAttemptStatus::PROCESSING, FulfillmentAttemptStatus::SUBMITTED])
                ->where('started_at', '<=', $processingCutoff)
                ->count(),
            'uncertain_escalation_due' => (int) FulfillmentAttempt::query()
                ->whereIn('status', [FulfillmentAttemptStatus::UNCERTAIN, FulfillmentAttemptStatus::SUBMITTED])
                ->where('started_at', '<=', $uncertainCutoff)
                ->count(),
        ];
    }
}
