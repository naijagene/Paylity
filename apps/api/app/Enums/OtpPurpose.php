<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case CHECKOUT = 'checkout';
    case REGISTRATION = 'registration';
    case WALLET = 'wallet';
    case PASSWORD_RESET = 'password_reset';
    case SENSITIVE_ACTION = 'sensitive_action';
}
