<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderService extends Model
{
    protected $fillable = [
        'provider',
        'category_key',
        'service_id',
        'service_name',
        'display_name',
        'is_active',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProviderVariation::class);
    }
}
