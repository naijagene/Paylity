<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaunchVoucherCampaign extends Model
{
    protected $fillable = [
        'name',
        'amount',
        'network',
        'generated_count',
        'redeemed_count',
        'expires_at',
        'active',
        'one_per_phone',
        'one_per_email',
        'one_per_device',
        'shared_code',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'generated_count' => 'integer',
            'redeemed_count' => 'integer',
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

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
