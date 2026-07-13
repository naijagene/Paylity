<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'product_type',
        'customer_phone',
        'customer_email',
        'customer_name',
        'product_amount',
        'convenience_fee',
        'gateway_fee',
        'payable_amount',
        'currency',
        'status',
        'payment_provider',
        'payment_reference',
        'payment_authorization_url',
        'fulfillment_provider',
        'fulfillment_reference',
        'request_payload',
        'response_payload',
        'failure_reason',
        'needs_manual_review',
        'manual_review_reason',
        'manual_review_at',
        'fulfillment_retry_count',
        'next_fulfillment_retry_at',
        'ip_address',
        'user_agent',
        'verified_phone',
        'receipt_verification_token',
        'fulfilled_at',
    ];

    protected function casts(): array
    {
        return [
            'product_amount' => 'integer',
            'convenience_fee' => 'integer',
            'gateway_fee' => 'integer',
            'payable_amount' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'verified_phone' => 'boolean',
            'needs_manual_review' => 'boolean',
            'fulfillment_retry_count' => 'integer',
            'manual_review_at' => 'datetime',
            'next_fulfillment_retry_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(TransactionEvent::class);
    }

    public function fulfillmentAttempts(): HasMany
    {
        return $this->hasMany(FulfillmentAttempt::class);
    }

    public function opsNotes(): HasMany
    {
        return $this->hasMany(OpsNote::class);
    }

    public function ledgerTransactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class);
    }

    public function financial(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TransactionFinancial::class);
    }
}
