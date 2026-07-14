<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaunchVoucherRedemption extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_REDEEMED = 'redeemed';

    public const STATUS_RELEASED = 'released';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    /** @deprecated Use STATUS_RESERVED */
    public const STATUS_PENDING = self::STATUS_RESERVED;

    /** @deprecated Use STATUS_REDEEMED */
    public const STATUS_COMPLETED = self::STATUS_REDEEMED;

    protected $fillable = [
        'launch_voucher_id',
        'transaction_id',
        'customer_phone',
        'customer_email',
        'device_id',
        'status',
        'discount_amount',
        'reserved_at',
        'redeemed_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'integer',
            'reserved_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'released_at' => 'datetime',
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
