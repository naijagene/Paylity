<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerTransaction extends Model
{
    protected $fillable = [
        'transaction_id',
        'source_type',
        'source_id',
        'transaction_reference',
        'event_type',
        'idempotency_key',
        'description',
        'status',
        'metadata',
        'operator_id',
        'posted_at',
        'reversed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'posted_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_id');
    }
}
