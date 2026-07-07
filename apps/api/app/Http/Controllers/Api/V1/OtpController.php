<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OtpPurpose;
use App\Exceptions\OtpException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Otp\OtpRequestRequest;
use App\Http\Requests\Api\V1\Otp\OtpResendRequest;
use App\Http\Requests\Api\V1\Otp\OtpVerifyRequest;
use App\Services\Otp\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otpService,
    ) {
    }

    public function request(OtpRequestRequest $request): JsonResponse
    {
        try {
            $data = $this->otpService->request(
                phone: $request->string('phone')->toString(),
                purpose: OtpPurpose::from($request->string('purpose')->toString()),
                email: $request->input('email'),
                reference: $request->input('reference'),
                amount: $request->integer('amount') ?: null,
                productType: $request->input('product_type'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (OtpException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: $exception->status,
            );
        }

        return ApiResponse::success(
            data: $data,
            message: 'Verification code sent.',
            status: 201,
        );
    }

    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        try {
            $data = $this->otpService->verify(
                otpReference: $request->string('otp_reference')->toString(),
                code: $request->string('code')->toString(),
            );
        } catch (OtpException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: $exception->status,
            );
        }

        return ApiResponse::success(
            data: $data,
            message: 'Phone verified successfully.',
        );
    }

    public function resend(OtpResendRequest $request): JsonResponse
    {
        try {
            $data = $this->otpService->resend(
                otpReference: $request->string('otp_reference')->toString(),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (OtpException $exception) {
            return ApiResponse::error(
                message: $exception->getMessage(),
                errors: ['code' => $exception->errorCode],
                status: $exception->status,
            );
        }

        return ApiResponse::success(
            data: $data,
            message: 'Verification code resent.',
        );
    }
}
