<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionFinancial extends Model
{
    protected $fillable = [
        'transaction_id',
        'provider_cost_kobo',
        'provider_cost_source',
        'provider_cost_status',
        'gross_margin_kobo',
        'gateway_fee_expected_kobo',
        'gateway_fee_actual_kobo',
        'settlement_status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
