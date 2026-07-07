<?php

namespace App\Services\Otp;

use App\Contracts\Otp\OtpProviderInterface;
use App\Enums\OtpPurpose;
use App\Enums\OtpStatus;
use App\Exceptions\OtpException;
use App\Models\OtpVerification;
use App\Services\Otp\Providers\LogOtpProvider;
use App\Services\Otp\Providers\SmsOtpProvider;
use App\Services\Platform\FeatureFlagService;
use App\Services\Platform\SystemSettingsService;
use App\Support\PhoneNormalizer;
use App\Support\Platform\FeatureFlagKeys;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OtpService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
        private readonly FeatureFlagService $featureFlags,
        private readonly OtpCodeGenerator $codeGenerator,
        private readonly LogOtpProvider $logProvider,
        private readonly SmsOtpProvider $smsProvider,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->featureFlags->isEnabled(FeatureFlagKeys::OTP_VERIFICATION)
            && $this->settings->getBool(SystemSettingKeys::OTP_ENABLED, true);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     otp_reference: string,
     *     expires_at: string,
     *     resend_available_at: string,
     *     masked_phone: string
     * }
     */
    public function request(
        string $phone,
        OtpPurpose $purpose,
        ?string $email = null,
        ?string $reference = null,
        ?int $amount = null,
        ?string $productType = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        if (! $this->isEnabled()) {
            throw new OtpException('OTP verification is currently unavailable.', 'OTP_DISABLED', 503);
        }

        $normalizedPhone = PhoneNormalizer::normalize($phone);

        if (! PhoneNormalizer::isValidNigerianPhone($normalizedPhone)) {
            throw new OtpException('Enter a valid Nigerian phone number.', 'INVALID_PHONE');
        }

        $cooldownSeconds = $this->settings->getInt(SystemSettingKeys::OTP_RESEND_COOLDOWN_SECONDS, 60);
        $recentPending = OtpVerification::query()
            ->where('phone', $normalizedPhone)
            ->where('purpose', $purpose)
            ->where('status', OtpStatus::PENDING)
            ->where('created_at', '>=', Carbon::now()->subSeconds($cooldownSeconds))
            ->latest('id')
            ->first();

        if ($recentPending) {
            throw new OtpException(
                'Please wait before requesting another code.',
                'OTP_RESEND_COOLDOWN',
                429,
            );
        }

        OtpVerification::query()
            ->where('phone', $normalizedPhone)
            ->where('purpose', $purpose)
            ->where('status', OtpStatus::PENDING)
            ->update(['status' => OtpStatus::EXPIRED]);

        $code = $this->codeGenerator->generate();
        $expiresAt = Carbon::now()->addMinutes(
            $this->settings->getInt(SystemSettingKeys::OTP_EXPIRY_MINUTES, 10),
        );

        $metadata = array_filter([
            'amount' => $amount,
            'product_type' => $productType,
            'client_reference' => $reference,
        ], static fn ($value) => $value !== null && $value !== '');

        $otpReference = 'OTP-'.Str::upper(Str::random(12));

        $record = OtpVerification::query()->create([
            'purpose' => $purpose,
            'phone' => $normalizedPhone,
            'email' => $email,
            'code_hash' => Hash::make($code),
            'reference' => $otpReference,
            'status' => OtpStatus::PENDING,
            'attempts' => 0,
            'max_attempts' => $this->settings->getInt(SystemSettingKeys::OTP_MAX_ATTEMPTS, 5),
            'expires_at' => $expiresAt,
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        $message = $this->buildMessage($code);
        $this->provider()->send($normalizedPhone, $message);

        return [
            'otp_reference' => $record->reference,
            'expires_at' => $expiresAt->toIso8601String(),
            'resend_available_at' => Carbon::now()->addSeconds($cooldownSeconds)->toIso8601String(),
            'masked_phone' => PhoneNormalizer::mask($normalizedPhone),
        ];
    }

    /**
     * @return array{verified: bool, verification_token: string}
     */
    public function verify(string $otpReference, string $code): array
    {
        $record = $this->findByReference($otpReference);
        $this->assertPending($record);

        if ($record->expires_at->isPast()) {
            $record->update(['status' => OtpStatus::EXPIRED]);
            throw new OtpException('This verification code has expired.', 'OTP_EXPIRED');
        }

        if ($record->attempts >= $record->max_attempts) {
            $record->update(['status' => OtpStatus::FAILED]);
            throw new OtpException('Maximum verification attempts exceeded.', 'OTP_MAX_ATTEMPTS');
        }

        if (! Hash::check($code, $record->code_hash)) {
            $attempts = $record->attempts + 1;
            $record->update([
                'attempts' => $attempts,
                'status' => $attempts >= $record->max_attempts ? OtpStatus::FAILED : OtpStatus::PENDING,
            ]);

            throw new OtpException(
                $attempts >= $record->max_attempts
                    ? 'Maximum verification attempts exceeded.'
                    : 'Invalid verification code.',
                $attempts >= $record->max_attempts ? 'OTP_MAX_ATTEMPTS' : 'OTP_INVALID',
            );
        }

        $verificationToken = Str::uuid()->toString();
        $verificationExpiresAt = Carbon::now()->addMinutes(30);

        $metadata = $record->metadata ?? [];
        $metadata['verification_token_hash'] = Hash::make($verificationToken);
        $metadata['verification_expires_at'] = $verificationExpiresAt->toIso8601String();

        $record->update([
            'status' => OtpStatus::VERIFIED,
            'verified_at' => Carbon::now(),
            'metadata' => $metadata,
        ]);

        return [
            'verified' => true,
            'verification_token' => $verificationToken,
        ];
    }

    /**
     * @return array{otp_reference: string, expires_at: string, resend_available_at: string, masked_phone: string}
     */
    public function resend(string $otpReference, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $record = $this->findByReference($otpReference);
        $this->assertPending($record);

        $cooldownSeconds = $this->settings->getInt(SystemSettingKeys::OTP_RESEND_COOLDOWN_SECONDS, 60);
        $secondsSinceCreated = $record->created_at?->diffInSeconds(Carbon::now()) ?? $cooldownSeconds;

        if ($secondsSinceCreated < $cooldownSeconds) {
            throw new OtpException(
                'Please wait before requesting another code.',
                'OTP_RESEND_COOLDOWN',
                429,
            );
        }

        $metadata = $record->metadata ?? [];

        return $this->request(
            phone: $record->phone,
            purpose: $record->purpose,
            email: $record->email,
            reference: $metadata['client_reference'] ?? null,
            amount: isset($metadata['amount']) ? (int) $metadata['amount'] : null,
            productType: $metadata['product_type'] ?? null,
            ipAddress: $ipAddress ?? $record->ip_address,
            userAgent: $userAgent ?? $record->user_agent,
        );
    }

    public function assertValidVerificationToken(
        string $verificationToken,
        string $phone,
        int $productAmount,
        OtpPurpose $purpose = OtpPurpose::CHECKOUT,
    ): OtpVerification {
        $normalizedPhone = PhoneNormalizer::normalize($phone);

        $records = OtpVerification::query()
            ->where('phone', $normalizedPhone)
            ->where('purpose', $purpose)
            ->where('status', OtpStatus::VERIFIED)
            ->whereNotNull('verified_at')
            ->latest('verified_at')
            ->limit(20)
            ->get();

        foreach ($records as $record) {
            $metadata = $record->metadata ?? [];
            $tokenHash = $metadata['verification_token_hash'] ?? null;
            $expiresAt = isset($metadata['verification_expires_at'])
                ? Carbon::parse($metadata['verification_expires_at'])
                : null;

            if (! $tokenHash || ! Hash::check($verificationToken, $tokenHash)) {
                continue;
            }

            if ($expiresAt?->isPast()) {
                throw new OtpException('Verification token has expired.', 'OTP_TOKEN_EXPIRED');
            }

            if (isset($metadata['consumed_at'])) {
                throw new OtpException('Verification token has already been used.', 'OTP_TOKEN_CONSUMED');
            }

            if ($purpose === OtpPurpose::CHECKOUT) {
                $expectedAmount = isset($metadata['amount']) ? (int) $metadata['amount'] : null;

                if ($expectedAmount !== null && $expectedAmount !== $productAmount) {
                    throw new OtpException(
                        'Verification token does not match this purchase amount.',
                        'OTP_TOKEN_AMOUNT_MISMATCH',
                    );
                }
            }

            return $record;
        }

        throw new OtpException('A valid phone verification is required.', 'OTP_VERIFICATION_REQUIRED');
    }

    public function consumeVerificationToken(OtpVerification $record): void
    {
        $metadata = $record->metadata ?? [];
        $metadata['consumed_at'] = Carbon::now()->toIso8601String();

        $record->update(['metadata' => $metadata]);
    }

    public function resolveVerifiedPhone(string $verificationToken, string $phone, int $productAmount): bool
    {
        if ($verificationToken === '') {
            return false;
        }

        try {
            $record = $this->assertValidVerificationToken($verificationToken, $phone, $productAmount);
            $this->consumeVerificationToken($record);

            return true;
        } catch (OtpException) {
            return false;
        }
    }

    private function provider(): OtpProviderInterface
    {
        $configuredProvider = $this->settings->getString(SystemSettingKeys::OTP_PROVIDER, 'log');

        if ($this->featureFlags->isEnabled(FeatureFlagKeys::SMS_PROVIDER_LIVE) && $configuredProvider !== 'log') {
            return $this->smsProvider;
        }

        return $this->logProvider;
    }

    private function buildMessage(string $code): string
    {
        $template = $this->settings->getString(
            SystemSettingKeys::OTP_MESSAGE_TEMPLATE,
            'Your PAYLITY verification code is :code. It expires in :minutes minutes.',
        );

        return str_replace(
            [':code', ':minutes'],
            [$code, (string) $this->settings->getInt(SystemSettingKeys::OTP_EXPIRY_MINUTES, 10)],
            $template,
        );
    }

    private function findByReference(string $otpReference): OtpVerification
    {
        $record = OtpVerification::query()->where('reference', $otpReference)->first();

        if (! $record) {
            throw new OtpException('Verification request not found.', 'OTP_NOT_FOUND', 404);
        }

        return $record;
    }

    private function assertPending(OtpVerification $record): void
    {
        if ($record->status === OtpStatus::VERIFIED) {
            throw new OtpException('This phone number is already verified.', 'OTP_ALREADY_VERIFIED');
        }

        if ($record->status === OtpStatus::FAILED) {
            throw new OtpException('Maximum verification attempts exceeded.', 'OTP_MAX_ATTEMPTS');
        }

        if ($record->status === OtpStatus::EXPIRED) {
            throw new OtpException('This verification code has expired.', 'OTP_EXPIRED');
        }
    }
}
