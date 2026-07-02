<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PaystackController;
use App\Http\Controllers\Api\V1\TransactionController;

Route::get('/health', HealthController::class);

Route::post('/checkout/initialize', [CheckoutController::class, 'initialize']);

Route::get('/transactions/{reference}', [TransactionController::class, 'show']);

Route::post('/payments/paystack/callback', [PaystackController::class, 'callback']);
Route::get('/payments/paystack/verify/{reference}', [PaystackController::class, 'verify']);
Route::post('/payments/paystack/webhook', [PaystackController::class, 'webhook']);
