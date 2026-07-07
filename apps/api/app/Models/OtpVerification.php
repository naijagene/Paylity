<?php

namespace App\Models;

use App\Enums\OtpPurpose;
use App\Enums\OtpStatus;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $fillable = [
        'purpose',
        'phone',
        'email',
        'code_hash',
        'reference',
        'transaction_reference',
        'status',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'status' => OtpStatus::class,
            'metadata' => 'array',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
