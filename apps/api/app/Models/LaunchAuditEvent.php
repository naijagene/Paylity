<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaunchAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'operator',
        'ip_address',
        'user_agent',
        'previous_values',
        'new_values',
        'reference',
        'run_id',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
