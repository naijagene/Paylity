<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Support\CorsOriginResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('transaction-lookup', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('payment-verify', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('ops', function (Request $request) {
            $operatorKey = (string) $request->header('X-Operator-Key', '');

            return Limit::perMinute(60)->by($operatorKey !== '' ? $operatorKey : $request->ip());
        });

        RateLimiter::for('receipt-verify', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('otp-request', function (Request $request) {
            $phone = (string) $request->input('phone', '');

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(3)->by($phone !== '' ? $phone : $request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $reference = (string) $request->input('otp_reference', '');

            return [
                Limit::perMinute(20)->by($request->ip()),
                Limit::perMinute(10)->by($reference !== '' ? $reference : $request->ip()),
            ];
        });

        RateLimiter::for('health', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('catalog', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        config(['cors.allowed_origins' => CorsOriginResolver::allowedOrigins()]);
    }
}
