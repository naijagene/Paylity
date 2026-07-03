<?php

namespace App\Services\Notifications;

use App\Enums\TransactionStatus;
use App\Mail\DeliveryFailureMail;
use App\Mail\DeliverySuccessMail;
use App\Mail\RetrySuccessMail;
use App\Mail\TransactionReceiptMail;
use App\Models\Transaction;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TransactionNotificationService
{
    public function __construct(
        private readonly ReceiptService $receiptService,
    ) {
    }

    public function sendReceipt(Transaction $transaction): void
    {
        if (! $this->hasEmail($transaction) || ! $this->receiptService->isReceiptAvailable($transaction)) {
            return;
        }

        $this->dispatch(
            new TransactionReceiptMail($transaction, $this->receiptService->buildReceiptPayload($transaction) ?? []),
            $transaction,
            'receipt',
        );
    }

    public function sendDeliverySuccess(Transaction $transaction): void
    {
        if (! $this->hasEmail($transaction) || $transaction->status !== TransactionStatus::FULFILLED) {
            return;
        }

        $this->dispatch(
            new DeliverySuccessMail($transaction),
            $transaction,
            'delivery_success',
        );
    }

    public function sendDeliveryFailure(Transaction $transaction): void
    {
        if (! $this->hasEmail($transaction) || $transaction->status !== TransactionStatus::FAILED) {
            return;
        }

        $this->dispatch(
            new DeliveryFailureMail($transaction),
            $transaction,
            'delivery_failure',
        );
    }

    public function sendRetrySuccess(Transaction $transaction): void
    {
        if (! $this->hasEmail($transaction) || $transaction->status !== TransactionStatus::FULFILLED) {
            return;
        }

        $this->dispatch(
            new RetrySuccessMail($transaction),
            $transaction,
            'retry_success',
        );
    }

    private function hasEmail(Transaction $transaction): bool
    {
        return is_string($transaction->customer_email) && $transaction->customer_email !== '';
    }

    private function dispatch(object $mailable, Transaction $transaction, string $type): void
    {
        try {
            Mail::to($transaction->customer_email)->send($mailable);
        } catch (\Throwable $exception) {
            Log::warning('Transaction notification failed.', [
                'type' => $type,
                'reference' => $transaction->reference,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
