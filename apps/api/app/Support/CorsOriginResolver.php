<?php

namespace App\Support;

class CorsOriginResolver
{
    /**
     * @return list<string>
     */
    public static function allowedOrigins(): array
    {
        $localOrigins = [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
        ];

        $frontendUrl = config('app.frontend_url');
        $normalizedFrontendUrl = $frontendUrl ? rtrim((string) $frontendUrl, '/') : null;

        $origins = config('app.env') === 'production'
            ? array_filter([$normalizedFrontendUrl])
            : array_merge($localOrigins, array_filter([$normalizedFrontendUrl]));

        $extra = config('cors.allowed_origins_extra', '');
        if (is_string($extra) && $extra !== '') {
            $extraOrigins = array_map(
                static fn (string $origin): string => rtrim(trim($origin), '/'),
                explode(',', $extra),
            );
            $origins = array_merge($origins, array_filter($extraOrigins));
        }

        return array_values(array_unique(array_filter($origins)));
    }

    public static function isAllowed(?string $origin): bool
    {
        if ($origin === null || $origin === '') {
            return false;
        }

        return in_array(rtrim($origin, '/'), self::allowedOrigins(), true);
    }
}
