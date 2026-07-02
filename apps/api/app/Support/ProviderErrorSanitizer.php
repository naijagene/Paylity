<?php

namespace App\Support;

use App\Exceptions\PaystackConfigurationException;
use App\Exceptions\PaystackException;
use App\Exceptions\VTPassConfigurationException;
use App\Exceptions\VTPassException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProviderErrorSanitizer
{
    public const PAYMENT_UNAVAILABLE = 'Payment provider is temporarily unavailable. Please try again shortly.';

    public const FULFILLMENT_UNAVAILABLE = 'Service delivery provider is temporarily unavailable. Please contact support.';

    public static function shouldSanitize(Throwable $exception): bool
    {
        return $exception instanceof PaystackException
            || $exception instanceof PaystackConfigurationException
            || $exception instanceof VTPassException
            || $exception instanceof VTPassConfigurationException;
    }

    public static function customerMessage(Throwable $exception): string
    {
        if (config('app.debug')) {
            return $exception->getMessage();
        }

        if ($exception instanceof PaystackException || $exception instanceof PaystackConfigurationException) {
            return self::PAYMENT_UNAVAILABLE;
        }

        if ($exception instanceof VTPassException || $exception instanceof VTPassConfigurationException) {
            return self::FULFILLMENT_UNAVAILABLE;
        }

        return $exception->getMessage();
    }

    public static function logProviderError(string $context, Throwable $exception, array $contextData = []): void
    {
        Log::error($context, array_merge($contextData, [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'code' => property_exists($exception, 'errorCode') ? $exception->errorCode : null,
        ]));
    }
}
