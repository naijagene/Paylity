<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Support\ApiResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(function (): void {
            app(\App\Services\Launch\SchedulerHeartbeatService::class)->record();
        })->everyMinute()->name('scheduler-heartbeat')->evenInMaintenanceMode();

        $schedule->command('paylity:reconcile-payments')
            ->everyTenMinutes()
            ->withoutOverlapping(15)
            ->onOneServer();
        $schedule->command('paylity:reconcile-fulfillments')
            ->everyTenMinutes()
            ->withoutOverlapping(15)
            ->onOneServer();
        $schedule->command('paylity:process-fulfillment-retries')
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->onOneServer();
        $schedule->command('paylity:reconcile-settlements')
            ->everyThirtyMinutes()
            ->withoutOverlapping(35)
            ->onOneServer();
        $schedule->command('paylity:financial-close')
            ->dailyAt('01:00')
            ->withoutOverlapping(60)
            ->onOneServer();
        $schedule->command('paylity:financial-alert-scan')
            ->everyFifteenMinutes()
            ->withoutOverlapping(20)
            ->onOneServer();
        $schedule->command('paylity:cleanup-otp')->dailyAt('02:30')->onOneServer();
        $schedule->command('paylity:cleanup-voucher-reservations')->everyFiveMinutes()->onOneServer();
        $schedule->command('paylity:cleanup-webhooks')->weeklyOn(0, '03:00')->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'operator' => \App\Http\Middleware\VerifyOperatorKey::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
        ]);

        $middleware->prepend([
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
        ]);

        $middleware->append([
            \App\Http\Middleware\CompressApiResponseMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    message: 'The given data was invalid.',
                    errors: $exception->errors(),
                    status: 422,
                );
            }
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error(
                    message: 'Too many requests. Please try again shortly.',
                    errors: ['code' => 'RATE_LIMIT_EXCEEDED'],
                    status: 429,
                );
            }
        });
    })->create();
