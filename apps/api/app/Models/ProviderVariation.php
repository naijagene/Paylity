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
        'display_name',
        'amount',
        'fixed_price',
        'is_active',
        'is_visible',
        'is_popular',
        'sort_order',
        'customer_category',
        'validity_label',
        'data_size_label',
        'display_override',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'fixed_price' => 'boolean',
            'is_active' => 'boolean',
            'is_visible' => 'boolean',
            'is_popular' => 'boolean',
            'sort_order' => 'integer',
            'display_override' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function providerService(): BelongsTo
    {
        return $this->belongsTo(ProviderService::class);
    }
}
