<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $receipt
     */
    public function __construct(
        public Transaction $transaction,
        public array $receipt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PAYLITY receipt — '.$this->transaction->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.transaction-receipt',
            with: [
                'transaction' => $this->transaction,
                'receipt' => $this->receipt,
            ],
        );
    }
}
