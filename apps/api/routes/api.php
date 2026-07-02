<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Ops\OpsSummaryController;
use App\Http\Controllers\Api\V1\Ops\OpsTransactionController;
use App\Http\Controllers\Api\V1\PaystackController;
use App\Http\Controllers\Api\V1\TransactionController;

Route::get('/health', HealthController::class);

Route::middleware('throttle:checkout')->group(function () {
    Route::post('/checkout/initialize', [CheckoutController::class, 'initialize']);
});

Route::middleware('throttle:transaction-lookup')->group(function () {
    Route::get('/transactions/{reference}', [TransactionController::class, 'show']);
});

Route::post('/payments/paystack/callback', [PaystackController::class, 'callback']);

Route::middleware('throttle:payment-verify')->group(function () {
    Route::get('/payments/paystack/verify/{reference}', [PaystackController::class, 'verify']);
});

Route::post('/payments/paystack/webhook', [PaystackController::class, 'webhook']);

Route::middleware(['operator', 'throttle:ops'])->prefix('ops')->group(function () {
    Route::get('/summary', OpsSummaryController::class);
    Route::get('/transactions', [OpsTransactionController::class, 'index']);
    Route::get('/transactions/{reference}', [OpsTransactionController::class, 'show']);
    Route::post('/transactions/{reference}/fulfill', [OpsTransactionController::class, 'fulfill']);
});
