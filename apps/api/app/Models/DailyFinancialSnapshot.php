<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyFinancialSnapshot extends Model
{
    protected $fillable = [
        'snapshot_date',
        'metrics',
        'status',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'metrics' => 'array',
            'finalized_at' => 'datetime',
        ];
    }
}
