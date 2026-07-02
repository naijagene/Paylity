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

        return array_values(array_unique(array_filter($origins)));
    }
}
