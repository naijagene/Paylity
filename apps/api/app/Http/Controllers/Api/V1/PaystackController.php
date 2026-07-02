<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PaymentVerificationException;
use App\Exceptions\PaystackException;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentVerificationService;
use App\Services\Payments\PaystackService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaystackController extends Controller
{
    public function __construct(
        private readonly PaystackService $paystackService,
        private readonly PaymentVerificationService $paymentVerificationService,
    ) {
    }

    public function callback(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'reference' => $request->query('reference') ?? $request->input('reference'),
                'status' => 'Callback received. Verify payment using the verify endpoint.',
            ],
            message: 'Paystack callback received. Payment is not confirmed from callback alone.',
        );
    }

    public function verify(string $reference): JsonResponse
    {
        try {
            $result = $this->paymentVerificationService->verify($reference);

            return ApiResponse::success(
                data: $this->paymentVerificationService->toVerifyResponse(
                    $result['transaction'],
                    $result['verification'],
                    $result['configured'],
                ),
                message: $result['configured']
                    ? 'Payment verification completed.'
                    : 'Paystack verification is not configured.',
            );
        } catch (PaymentVerificationException $exception) {
            $status = $exception->errorCode === 'TRANSACTION_NOT_FOUND' ? 404 : 422;

            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: $status,
            );
        } catch (PaystackException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: 502,
            );
        }
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Paystack-Signature');

        if (! $this->paystackService->validateWebhookSignature($payload, $signature)) {
            return ApiResponse::error(
                message: 'Invalid Paystack webhook signature.',
                errors: ['code' => 'INVALID_SIGNATURE'],
                status: 401,
            );
        }

        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return ApiResponse::error(
                message: 'Invalid Paystack webhook payload.',
                errors: ['code' => 'INVALID_PAYLOAD'],
                status: 400,
            );
        }

        if (data_get($event, 'event') === 'charge.success') {
            $reference = (string) data_get($event, 'data.reference');

            if ($reference !== '') {
                try {
                    $this->paymentVerificationService->verify($reference);
                } catch (PaymentVerificationException|PaystackException $exception) {
                    Log::warning('Paystack webhook verification failed.', [
                        'reference' => $reference,
                        'message' => $exception->getMessage(),
                        'code' => $exception instanceof PaymentVerificationException
                            ? $exception->errorCode
                            : $exception->errorCode,
                    ]);
                }
            }
        }

        return ApiResponse::success(
            data: ['status' => 'received'],
            message: 'Paystack webhook processed.',
        );
    }
}
