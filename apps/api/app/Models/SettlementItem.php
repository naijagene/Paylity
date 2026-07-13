<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    protected $fillable = [
        'settlement_batch_id',
        'transaction_id',
        'transaction_reference',
        'expected_amount_kobo',
        'actual_amount_kobo',
        'difference_kobo',
        'status',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SettlementBatch::class, 'settlement_batch_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
