<?php

namespace App\Exceptions;

use Exception;

class ProductCatalogValidationException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'INVALID_PRODUCT_VARIATION',
    ) {
        parent::__construct($message);
    }
}
