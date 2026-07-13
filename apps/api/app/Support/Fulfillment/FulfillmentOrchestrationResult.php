<?php

namespace App\Support\Fulfillment;

use App\Models\FulfillmentAttempt;
use App\Models\Transaction;

readonly class FulfillmentOrchestrationResult
{
    public function __construct(
        public string $outcome,
        public Transaction $transaction,
        public ?string $reason = null,
        public ?FulfillmentAttempt $attempt = null,
    ) {
    }

    public function fulfilled(): bool
    {
        return $this->outcome === 'fulfilled';
    }

    public function ignored(): bool
    {
        return in_array($this->outcome, [
            'already_fulfilled',
            'ignored',
            'manual_review',
            'active_attempt',
            'payment_not_confirmed',
        ], true);
    }
}
