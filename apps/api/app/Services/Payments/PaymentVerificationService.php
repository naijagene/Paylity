<?php

namespace App\Services\Payments;

use App\Enums\TransactionStatus;
use App\Exceptions\PaymentVerificationException;
use App\Models\Transaction;
use App\Exceptions\PaystackException;
use App\Services\Finance\LedgerPostingService;
use App\Services\Fulfillment\AutoFulfillmentRecorder;
use App\Services\Fulfillment\ExactOnceFulfillmentService;
use App\Services\Fulfillment\FulfillmentRetryService;
use App\Services\Fulfillment\VTPassService;
use App\Services\Marketing\LaunchVoucherService;
use App\Services\Notifications\TransactionNotificationService;
use App\Services\ReceiptService;
use App\Services\TransactionEventService;

class PaymentVerificationService
{
    private const TERMINAL_PAYMENT_STATUSES = [
        TransactionStatus::PAYMENT_SUCCESS,
        TransactionStatus::PAYMENT_FAILED,
        TransactionStatus::FULFILLED,
        TransactionStatus::FULFILLMENT_PENDING,
        TransactionStatus::FAILED,
    ];

    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly VTPassService $vtpassService,
        private readonly ExactOnceFulfillmentService $exactOnceFulfillmentService,
        private readonly AutoFulfillmentRecorder $autoFulfillmentRecorder,
        private readonly FulfillmentRetryService $fulfillmentRetryService,
        private readonly TransactionEventService $transactionEventService,
        private readonly TransactionNotificationService $transactionNotificationService,
        private readonly ReceiptService $receiptService,
        private readonly LedgerPostingService $ledgerPostingService,
        private readonly LaunchVoucherService $launchVoucherService,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->paystackService->isEnabled() && $this->paystackService->hasSecretKey();
    }

    /**
     * @return array{
     *     transaction: Transaction,
     *     verification: array<string, mixed>|null,
     *     configured: bool
     * }
     *
     * @throws PaymentVerificationException
     * @throws PaystackException
     */
    public function verify(string $reference): array
    {
        $transaction = Transaction::query()
            ->where('reference', $reference)
            ->first();

        if (! $transaction) {
            throw new PaymentVerificationException(
                'Transaction not found.',
                'TRANSACTION_NOT_FOUND',
            );
        }

        if (! $this->isConfigured()) {
            return [
                'transaction' => $transaction,
                'verification' => null,
                'configured' => false,
            ];
        }

        if ($this->isPaymentAlreadyVerified($transaction)) {
            return [
                'transaction' => $transaction->fresh(),
                'verification' => data_get($transaction->response_payload, 'verify'),
                'configured' => true,
            ];
        }

        $verification = $this->paystackService->verifyTransaction($reference);

        $this->assertReferenceMatches($transaction, $verification);
        $this->assertAmountMatches($transaction, $verification);
        $this->assertCurrencyMatches($verification);

        $transaction = $this->applyVerificationResult($transaction, $verification);
        $transaction = $this->maybeAutoFulfill($transaction);

        return [
            'transaction' => $transaction->fresh(),
            'verification' => $verification,
            'configured' => true,
        ];
    }

    private function isPaymentAlreadyVerified(Transaction $transaction): bool
    {
        if (! in_array($transaction->status, self::TERMINAL_PAYMENT_STATUSES, true)) {
            return false;
        }

        return data_get($transaction->response_payload, 'verify') !== null;
    }

    /**
     * @param  array<string, mixed>|null  $verification
     * @return array<string, mixed>
     */
    public function toVerifyResponse(Transaction $transaction, ?array $verification = null, bool $configured = true): array
    {
        $response = [
            'reference' => $transaction->reference,
            'status' => $transaction->status,
            'payment_status' => $this->paymentStatusLabel($transaction),
            'product_type' => $transaction->product_type,
            'product_amount' => $transaction->product_amount,
            'convenience_fee' => $transaction->convenience_fee,
            'gateway_fee' => $transaction->gateway_fee,
            'payable_amount' => $transaction->payable_amount,
            'currency' => $transaction->currency,
            'verified_at' => $this->verifiedAt($transaction, $verification),
            'fulfillment_status' => $this->fulfillmentStatus($transaction),
        ];

        if ($transaction->failure_reason) {
            $response['failure_reason'] = $transaction->failure_reason;
        }

        $receipt = $this->receiptService->buildReceiptPayload($transaction);

        if ($receipt !== null) {
            $response['receipt'] = $receipt;
        }

        if (! $configured) {
            $response['payment_status'] = 'Payment confirmation coming next.';
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function assertReferenceMatches(Transaction $transaction, array $verification): void
    {
        if ($verification['reference'] !== $transaction->reference) {
            throw new PaymentVerificationException(
                'Paystack reference does not match PAYLITY transaction reference.',
                'REFERENCE_MISMATCH',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function assertAmountMatches(Transaction $transaction, array $verification): void
    {
        $expectedKobo = $transaction->payable_amount * 100;

        if ((int) $verification['amount'] !== $expectedKobo) {
            throw new PaymentVerificationException(
                'Paystack amount does not match PAYLITY payable amount.',
                'AMOUNT_MISMATCH',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function assertCurrencyMatches(array $verification): void
    {
        if (strtoupper((string) $verification['currency']) !== 'NGN') {
            throw new PaymentVerificationException(
                'Paystack currency must be NGN.',
                'CURRENCY_MISMATCH',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $verification
     */
    private function applyVerificationResult(Transaction $transaction, array $verification): Transaction
    {
        $paystackStatus = strtolower((string) $verification['status']);

        if ($paystackStatus === 'success') {
            $transaction->update([
                'status' => TransactionStatus::PAYMENT_SUCCESS,
                'payment_reference' => $verification['reference'],
                'response_payload' => array_merge(
                    (array) $transaction->response_payload,
                    ['verify' => $verification['raw_response']],
                ),
                'failure_reason' => null,
            ]);

            $this->transactionEventService->record(
                $transaction->fresh(),
                TransactionEventService::TYPE_PAYMENT_SUCCESS,
                'Payment verified successfully.',
            );
            $this->receiptService->ensureVerificationToken($transaction->fresh());
            $this->transactionNotificationService->sendReceipt($transaction->fresh());
            $this->ledgerPostingService->postPaymentReceived($transaction->fresh());

            return $transaction;
        }

        if (in_array($paystackStatus, ['failed', 'abandoned'], true)) {
            $transaction->update([
                'status' => TransactionStatus::PAYMENT_FAILED,
                'failure_reason' => (string) ($verification['gateway_response'] ?: 'Payment failed.'),
                'response_payload' => array_merge(
                    (array) $transaction->response_payload,
                    ['verify' => $verification['raw_response']],
                ),
            ]);

            $this->transactionEventService->record(
                $transaction->fresh(),
                TransactionEventService::TYPE_PAYMENT_FAILED,
                'Payment failed.',
                'system',
                ['reason' => $transaction->fresh()->failure_reason],
            );

            $this->launchVoucherService->releaseReservation($transaction->fresh(), 'payment_failed');

            return $transaction;
        }

        $transaction->update([
            'status' => TransactionStatus::PAYMENT_PENDING,
            'response_payload' => array_merge(
                (array) $transaction->response_payload,
                ['verify' => $verification['raw_response']],
            ),
        ]);

        $this->transactionEventService->record(
            $transaction->fresh(),
            TransactionEventService::TYPE_PAYMENT_PENDING,
            'Payment is pending.',
        );

        return $transaction;
    }

    private function maybeAutoFulfill(Transaction $transaction): Transaction
    {
        if ($transaction->status !== TransactionStatus::PAYMENT_SUCCESS) {
            return $this->autoFulfillmentRecorder->recordSkip(
                $transaction,
                AutoFulfillmentRecorder::SKIP_NOT_PAYMENT_SUCCESS,
            );
        }

        if (! $this->vtpassService->isEnabled()) {
            return $this->autoFulfillmentRecorder->recordSkip(
                $transaction,
                AutoFulfillmentRecorder::SKIP_VTPASS_DISABLED,
            );
        }

        if (! $this->vtpassService->isAutoFulfillEnabled()) {
            return $this->autoFulfillmentRecorder->recordSkip(
                $transaction,
                AutoFulfillmentRecorder::SKIP_FEATURE_FLAG_OFF,
            );
        }

        $transaction = $this->autoFulfillmentRecorder->recordAttempt($transaction->fresh());

        $triggerSource = data_get($transaction->response_payload, 'verify.source', 'callback');
        $result = $triggerSource === 'webhook'
            ? $this->exactOnceFulfillmentService->requestFromWebhook($transaction->fresh())
            : $this->exactOnceFulfillmentService->requestFromCallback($transaction->fresh());

        if ($result->fulfilled()) {
            return $this->autoFulfillmentRecorder->recordSuccess($result->transaction);
        }

        if ($result->ignored()) {
            return $this->autoFulfillmentRecorder->recordSkip(
                $result->transaction,
                $result->reason ?? AutoFulfillmentRecorder::SKIP_FEATURE_FLAG_OFF,
            );
        }

        $fresh = $result->transaction->fresh();
        $reason = $fresh->failure_reason ?: ($result->reason ?? 'Fulfillment failed.');

        if ($result->outcome === 'uncertain') {
            return $this->autoFulfillmentRecorder->recordFailure($fresh, $reason);
        }

        if (! $fresh->failure_reason && $fresh->status === TransactionStatus::PAYMENT_SUCCESS) {
            $fresh->update(['failure_reason' => $reason]);
            $fresh = $fresh->fresh();
        }

        $recorded = $this->autoFulfillmentRecorder->recordFailure($fresh, $reason);
        $this->fulfillmentRetryService->scheduleAfterFailure($recorded, $reason);

        return $recorded;
    }

    private function paymentStatusLabel(Transaction $transaction): string
    {
        return match ($transaction->status) {
            TransactionStatus::PAYMENT_SUCCESS => 'Payment successful.',
            TransactionStatus::PAYMENT_FAILED => $transaction->failure_reason ?: 'Payment failed.',
            TransactionStatus::PAYMENT_PENDING => 'Payment pending.',
            default => 'Payment confirmation in progress.',
        };
    }

    private function fulfillmentStatus(Transaction $transaction): string
    {
        return match ($transaction->status) {
            TransactionStatus::FULFILLED => 'fulfilled',
            TransactionStatus::FULFILLMENT_PENDING => 'pending',
            TransactionStatus::FAILED => 'failed',
            TransactionStatus::PAYMENT_SUCCESS => 'awaiting_delivery',
            default => 'not_started',
        };
    }

    /**
     * @param  array<string, mixed>|null  $verification
     */
    private function verifiedAt(Transaction $transaction, ?array $verification): ?string
    {
        if ($transaction->status !== TransactionStatus::PAYMENT_SUCCESS
            && $transaction->status !== TransactionStatus::FULFILLED
            && $transaction->status !== TransactionStatus::FULFILLMENT_PENDING
        ) {
            return null;
        }

        $paidAt = $verification['paid_at'] ?? null;

        if (is_string($paidAt) && $paidAt !== '') {
            return $paidAt;
        }

        return $transaction->updated_at?->toIso8601String();
    }
}
