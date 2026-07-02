<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionStatus;
use App\Exceptions\PaystackConfigurationException;
use App\Exceptions\PaystackException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Payments\PaystackService;
use App\Services\TransactionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaystackController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly TransactionService $transactionService,
    ) {
    }

    public function callback(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'reference' => $request->query('reference') ?? $request->input('reference'),
                'status' => 'Payment confirmation coming next.',
            ],
            message: 'Paystack callback received.',
        );
    }

    public function verify(string $reference): JsonResponse
    {
        $transaction = Transaction::query()
            ->where('reference', $reference)
            ->first();

        if (! $transaction) {
            return ApiResponse::error(
                message: 'Transaction not found.',
                errors: ['code' => 'TRANSACTION_NOT_FOUND'],
                status: 404,
            );
        }

        if (! $this->paystackService->isEnabled() || ! $this->paystackService->hasSecretKey()) {
            return ApiResponse::success(
                data: [
                    'reference' => $transaction->reference,
                    'status' => $transaction->status,
                    'payment_status' => 'Payment confirmation coming next.',
                    'transaction' => $this->transactionService->toDetailResponse($transaction),
                ],
                message: 'Paystack verification is not configured.',
            );
        }

        try {
            $verification = $this->paystackService->verifyTransaction($reference);
            $paystackStatus = (string) data_get($verification, 'data.status');

            if ($paystackStatus === 'success') {
                $transaction->update([
                    'status' => TransactionStatus::PAYMENT_SUCCESS,
                    'response_payload' => array_merge(
                        (array) $transaction->response_payload,
                        ['verify' => $verification],
                    ),
                ]);
            }

            return ApiResponse::success(
                data: [
                    'reference' => $transaction->reference,
                    'status' => $transaction->fresh()->status,
                    'paystack_status' => $paystackStatus,
                    'transaction' => $this->transactionService->toDetailResponse($transaction->fresh()),
                ],
                message: 'Payment verification completed.',
            );
        } catch (PaystackConfigurationException|PaystackException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: 502,
            );
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: ['status' => 'Webhook received. Processing coming next.'],
            message: 'Paystack webhook received.',
        );
    }
}
