<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderVariation extends Model
{
    protected $fillable = [
        'provider_service_id',
        'variation_code',
        'name',
        'amount',
        'fixed_price',
        'is_active',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fixed_price' => 'boolean',
            'is_active' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function providerService(): BelongsTo
    {
        return $this->belongsTo(ProviderService::class);
    }
}
