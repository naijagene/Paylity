<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementBatch extends Model
{
    protected $fillable = [
        'provider',
        'settlement_date',
        'expected_amount_kobo',
        'actual_amount_kobo',
        'difference_kobo',
        'status',
        'metadata',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'settlement_date' => 'date',
            'metadata' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }
}
