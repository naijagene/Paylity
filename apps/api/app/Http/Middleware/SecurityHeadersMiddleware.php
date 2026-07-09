<?php

namespace App\Http\Middleware;

use App\Support\CorsOriginResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    private const CORS_ALLOW_METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';

    private const CORS_ALLOW_HEADERS = 'Content-Type, Authorization, X-Requested-With, X-Operator-Key, X-CSRF-TOKEN, Accept';

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigin = CorsOriginResolver::isAllowed($origin) ? rtrim((string) $origin, '/') : null;

        if ($request->isMethod('OPTIONS') && $allowedOrigin !== null) {
            $response = response('', Response::HTTP_NO_CONTENT);
            $this->applyCorsHeaders($response, $allowedOrigin);

            return $this->applySecurityHeaders($response, $request);
        }

        /** @var Response $response */
        $response = $next($request);

        if ($allowedOrigin !== null) {
            $this->applyCorsHeaders($response, $allowedOrigin);
        }

        return $this->applySecurityHeaders($response, $request);
    }

    private function applyCorsHeaders(Response $response, string $origin): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', self::CORS_ALLOW_METHODS);
        $response->headers->set('Access-Control-Allow-Headers', self::CORS_ALLOW_HEADERS);
        $response->headers->set('Access-Control-Allow-Credentials', 'false');
        $response->headers->set('Vary', 'Origin');
    }

    private function applySecurityHeaders(Response $response, Request $request): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=()',
        );

        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "img-src 'self' data: https:",
            "style-src 'self' 'unsafe-inline'",
            "script-src 'self'",
            "connect-src 'self' https:",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        if ($request->isSecure() || in_array((string) config('app.env'), ['production', 'staging'], true)) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
