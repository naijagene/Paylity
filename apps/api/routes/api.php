<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\FeatureFlagController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\ElectricityMeterController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\OtpController;
use App\Http\Controllers\Api\V1\Ops\OpsDashboardController;
use App\Http\Controllers\Api\V1\Ops\OpsMonitoringController;
use App\Http\Controllers\Api\V1\Ops\OpsNoteController;
use App\Http\Controllers\Api\V1\Ops\OpsReliabilityController;
use App\Http\Controllers\Api\V1\Ops\OpsReportsController;
use App\Http\Controllers\Api\V1\Ops\OpsSummaryController;
use App\Http\Controllers\Api\V1\Ops\OpsTransactionController;
use App\Http\Controllers\Api\V1\PaystackController;
use App\Http\Controllers\Api\V1\PlatformStatusController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\ReceiptVerificationController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransactionHistoryController;

Route::middleware('throttle:health')->group(function () {
    Route::get('/health', HealthController::class);
    Route::get('/platform/status', PlatformStatusController::class);
});

Route::middleware('throttle:catalog')->group(function () {
    Route::get('/catalog/products', [CatalogController::class, 'products']);
    Route::post('/electricity/meter/verify', [ElectricityMeterController::class, 'verify']);
});

Route::middleware('throttle:checkout')->group(function () {
    Route::post('/checkout/initialize', [CheckoutController::class, 'initialize']);
});

Route::middleware('throttle:otp-request')->group(function () {
    Route::post('/otp/request', [OtpController::class, 'request']);
});

Route::middleware('throttle:otp-verify')->group(function () {
    Route::post('/otp/verify', [OtpController::class, 'verify']);
    Route::post('/otp/resend', [OtpController::class, 'resend']);
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

Route::match(['get', 'post'], '/payments/paystack/callback', [PaystackController::class, 'callback']);

Route::middleware('throttle:payment-verify')->group(function () {
    Route::get('/payments/paystack/verify/{reference}', [PaystackController::class, 'verify']);
});

Route::middleware('throttle:webhook')->group(function () {
    Route::post('/payments/paystack/webhook', [PaystackController::class, 'webhook']);
});

Route::middleware(['operator', 'throttle:ops'])->group(function () {
    Route::get('/settings', [SettingsController::class, 'index']);
    Route::put('/settings', [SettingsController::class, 'update']);
    Route::get('/feature-flags', [FeatureFlagController::class, 'index']);
    Route::put('/feature-flags', [FeatureFlagController::class, 'update']);
});

Route::middleware(['operator', 'throttle:ops'])->prefix('ops')->group(function () {
    Route::get('/dashboard', OpsDashboardController::class);
    Route::get('/reliability', OpsReliabilityController::class);
    Route::get('/summary', OpsSummaryController::class);
    Route::get('/monitoring', OpsMonitoringController::class);
    Route::get('/reports/daily-reconciliation', [OpsReportsController::class, 'dailyReconciliation']);
    Route::get('/reports/failed-transactions', [OpsReportsController::class, 'failedTransactions']);
    Route::get('/reports/settlement-summary', [OpsReportsController::class, 'settlementSummary']);
    Route::get('/reports/retry-summary', [OpsReportsController::class, 'retrySummary']);
    Route::get('/transactions', [OpsTransactionController::class, 'index']);
    Route::get('/transactions/{reference}', [OpsTransactionController::class, 'show']);
    Route::post('/transactions/{reference}/fulfill', [OpsTransactionController::class, 'fulfill']);
    Route::post('/transactions/{reference}/retry-fulfillment', [OpsTransactionController::class, 'retry']);
    Route::get('/transactions/{reference}/notes', [OpsNoteController::class, 'index']);
    Route::post('/transactions/{reference}/notes', [OpsNoteController::class, 'store']);
});
