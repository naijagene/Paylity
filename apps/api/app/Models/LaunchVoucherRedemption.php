<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaunchVoucherRedemption extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'launch_voucher_id',
        'transaction_id',
        'customer_phone',
        'customer_email',
        'device_id',
        'status',
        'discount_amount',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(LaunchVoucher::class, 'launch_voucher_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
