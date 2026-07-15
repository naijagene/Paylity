<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaunchVoucherCampaign extends Model
{
    public const DISTRIBUTION_UNIQUE_CODES = 'unique_codes';

    public const DISTRIBUTION_SHARED_CODE = 'shared_code';

    protected $fillable = [
        'name',
        'amount',
        'network',
        'distribution_mode',
        'generated_count',
        'max_redemptions',
        'redeemed_count',
        'expires_at',
        'active',
        'one_per_phone',
        'one_per_email',
        'one_per_device',
        'reservation_timeout_minutes',
        'shared_code',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'generated_count' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'reservation_timeout_minutes' => 'integer',
            'expires_at' => 'datetime',
            'active' => 'boolean',
            'one_per_phone' => 'boolean',
            'one_per_email' => 'boolean',
            'one_per_device' => 'boolean',
            'shared_code' => 'boolean',
        ];
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(LaunchVoucher::class, 'campaign_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LaunchVoucherRedemption::class, 'campaign_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isSharedCode(): bool
    {
        return $this->distribution_mode === self::DISTRIBUTION_SHARED_CODE;
    }

    public function isUniqueCodes(): bool
    {
        return $this->distribution_mode === self::DISTRIBUTION_UNIQUE_CODES;
    }
}
