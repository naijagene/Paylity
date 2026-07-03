<?php

namespace App\Mail;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeliveryFailureMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Delivery issue — '.$this->transaction->reference,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.delivery-failure',
            with: ['transaction' => $this->transaction],
        );
    }
}
