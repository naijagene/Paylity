<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Ops\OpsMonitoringController;
use App\Http\Controllers\Api\V1\Ops\OpsNoteController;
use App\Http\Controllers\Api\V1\Ops\OpsSummaryController;
use App\Http\Controllers\Api\V1\Ops\OpsTransactionController;
use App\Http\Controllers\Api\V1\PaystackController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReceiptVerificationController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransactionHistoryController;

Route::get('/health', HealthController::class);

Route::get('/catalog/products', [CatalogController::class, 'products']);

Route::middleware('throttle:checkout')->group(function () {
    Route::post('/checkout/initialize', [CheckoutController::class, 'initialize']);
});

Route::middleware('throttle:transaction-lookup')->group(function () {
    Route::get('/transactions', [TransactionHistoryController::class, 'index']);
    Route::get('/transactions/{reference}', [TransactionController::class, 'show']);
    Route::get('/transactions/{reference}/receipt', [ReceiptController::class, 'show']);
    Route::get('/transactions/{reference}/receipt/download', [ReceiptController::class, 'download']);
});

Route::middleware('throttle:receipt-verify')->group(function () {
    Route::get('/receipts/verify/{token}', [ReceiptVerificationController::class, 'show']);
});

Route::post('/payments/paystack/callback', [PaystackController::class, 'callback']);

Route::middleware('throttle:payment-verify')->group(function () {
    Route::get('/payments/paystack/verify/{reference}', [PaystackController::class, 'verify']);
});

Route::middleware('throttle:webhook')->group(function () {
    Route::post('/payments/paystack/webhook', [PaystackController::class, 'webhook']);
});

Route::middleware(['operator', 'throttle:ops'])->prefix('ops')->group(function () {
    Route::get('/summary', OpsSummaryController::class);
    Route::get('/monitoring', OpsMonitoringController::class);
    Route::get('/transactions', [OpsTransactionController::class, 'index']);
    Route::get('/transactions/{reference}', [OpsTransactionController::class, 'show']);
    Route::post('/transactions/{reference}/fulfill', [OpsTransactionController::class, 'fulfill']);
    Route::post('/transactions/{reference}/retry-fulfillment', [OpsTransactionController::class, 'retry']);
    Route::get('/transactions/{reference}/notes', [OpsNoteController::class, 'index']);
    Route::post('/transactions/{reference}/notes', [OpsNoteController::class, 'store']);
});
