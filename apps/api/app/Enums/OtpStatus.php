<?php

namespace App\Enums;

enum OtpStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case EXPIRED = 'expired';
    case FAILED = 'failed';
}
