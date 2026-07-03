<?php

namespace App\Exceptions;

use Exception;

class VTPassException extends Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'VTPASS_ERROR',
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function safeContext(): array
    {
        return array_filter([
            'endpoint' => $this->context['endpoint'] ?? null,
            'http_status' => $this->context['http_status'] ?? null,
            'content_type' => $this->context['content_type'] ?? null,
            'vtpass_code' => $this->context['vtpass_code'] ?? null,
            'vtpass_message' => $this->context['vtpass_message'] ?? null,
        ], fn (mixed $value) => $value !== null && $value !== '');
    }
}
