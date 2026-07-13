<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FulfillmentAttempt extends Model
{
    protected $fillable = [
        'transaction_id',
        'attempt_number',
        'trigger_source',
        'provider',
        'request_id',
        'provider_reference',
        'provider_code',
        'provider_message',
        'status',
        'outcome',
        'duration_ms',
        'request_payload',
        'response_payload',
        'failure_reason',
        'error_class',
        'error_code',
        'error_message',
        'actor',
        'created_by_operator',
        'successful_attempt_key',
        'started_at',
        'submitted_at',
        'resolved_at',
        'next_retry_at',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'attempted_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
