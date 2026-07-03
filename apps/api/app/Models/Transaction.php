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
}
