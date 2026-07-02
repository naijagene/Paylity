<?php

namespace App\Exceptions;

class VTPassConfigurationException extends VTPassException
{
    public function __construct()
    {
        parent::__construct(
            'VTPass credentials are not configured.',
            'VTPASS_NOT_CONFIGURED',
        );
    }
}
