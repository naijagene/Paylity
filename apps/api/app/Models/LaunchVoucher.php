<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaunchVoucher extends Model
{
    public const ALLOWED_AMOUNTS = [500, 1000];

    protected $fillable = [
        'campaign_id',
        'name',
        'code',
        'code_normalized',
        'product_type',
        'amount',
        'network',
        'max_redemptions',
        'redeemed_count',
        'expires_at',
        'active',
        'one_per_phone',
        'one_per_email',
        'one_per_device',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
            'expires_at' => 'datetime',
            'active' => 'boolean',
            'one_per_phone' => 'boolean',
            'one_per_email' => 'boolean',
            'one_per_device' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(LaunchVoucherCampaign::class, 'campaign_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LaunchVoucherRedemption::class);
    }

    public function remainingRedemptions(): int
    {
        return max(0, $this->max_redemptions - $this->redeemed_count);
    }

    public function isExpired(): bool
    {
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return true;
        }

        return $this->campaign?->isExpired() ?? false;
    }

    public function hasReservationOrRedemption(): bool
    {
        return $this->redemptions()
            ->whereIn('status', [
                LaunchVoucherRedemption::STATUS_RESERVED,
                LaunchVoucherRedemption::STATUS_REDEEMED,
            ])
            ->exists();
    }
}
