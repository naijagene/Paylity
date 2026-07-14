<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingEvent extends Model
{
    protected $fillable = [
        'event_type',
        'reference',
        'transaction_id',
        'launch_voucher_id',
        'metadata',
        'actor',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LaunchVoucher::class, 'launch_voucher_id');
    }
}
