<?php

namespace App\Services\Launch;

use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Facades\Route;

class PaystackModeInspector
{
    public const MODE_TEST = 'test';

    public const MODE_LIVE = 'live';

    public const MODE_MISSING = 'missing';

    public const MODE_MIXED_INVALID = 'mixed_invalid';

    public const VERDICT_VALID = 'valid';

    public const VERDICT_INVALID = 'invalid';

    public function __construct(
        private readonly SystemSettingsService $settings,
        private readonly LaunchModeService $launchModeService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $enabled = (bool) config('services.paystack.enabled');
        $publicKey = (string) config('services.paystack.public_key');
        $secretKey = (string) config('services.paystack.secret_key');
        $callbackUrl = (string) config('services.paystack.callback_url');
        $webhookUrl = $this->webhookUrl();
        $environment = (string) config('app.env');
        $launchMode = $this->launchModeService->mode();

        $publicKeyMode = $this->keyMode($publicKey);
        $secretKeyMode = $this->keyMode($secretKey);
        $detectedMode = $this->detectMode($publicKeyMode, $secretKeyMode);
        $configurationComplete = $enabled
            && $publicKey !== ''
            && $secretKey !== ''
            && $callbackUrl !== ''
            && $detectedMode !== self::MODE_MIXED_INVALID
            && $detectedMode !== self::MODE_MISSING;

        $blockers = $this->blockers(
            enabled: $enabled,
            publicKeyMode: $publicKeyMode,
            secretKeyMode: $secretKeyMode,
            detectedMode: $detectedMode,
            configurationComplete: $configurationComplete,
            callbackUrl: $callbackUrl,
            environment: $environment,
            launchMode: $launchMode,
        );

        $verdict = $blockers === [] ? self::VERDICT_VALID : self::VERDICT_INVALID;

        return [
            'enabled' => $enabled,
            'detected_mode' => $detectedMode,
            'mode' => $detectedMode === self::MODE_MIXED_INVALID ? self::MODE_MIXED_INVALID : $detectedMode,
            'public_key_mode' => $publicKeyMode,
            'secret_key_mode' => $secretKeyMode,
            'public_key_prefix' => $this->safePrefix($publicKey),
            'secret_key_prefix' => $this->safePrefix($secretKey),
            'configuration_complete' => $configurationComplete,
            'callback_url' => $callbackUrl,
            'webhook_url' => $webhookUrl,
            'webhook_route' => '/api/v1/payments/paystack/webhook',
            'webhook_route_exists' => $this->webhookRouteExists(),
            'webhook_signature_verification_enabled' => $secretKey !== '',
            'environment' => $environment,
            'launch_mode' => $launchMode,
            'frontend_url' => (string) config('app.frontend_url'),
            'frontend_alignment' => $this->frontendAlignment($callbackUrl),
            'verdict' => $verdict,
            'blockers' => $blockers,
            'secret_configured' => $secretKey !== '',
            'public_configured' => $publicKey !== '',
        ];
    }

    public function isValid(): bool
    {
        return ($this->inspect()['verdict'] ?? self::VERDICT_INVALID) === self::VERDICT_VALID;
    }

    private function keyMode(string $key): string
    {
        if ($key === '') {
            return self::MODE_MISSING;
        }

        if (str_starts_with($key, 'pk_live_') || str_starts_with($key, 'sk_live_')) {
            return self::MODE_LIVE;
        }

        if (str_starts_with($key, 'pk_test_') || str_starts_with($key, 'sk_test_')) {
            return self::MODE_TEST;
        }

        return self::MODE_MISSING;
    }

    private function detectMode(string $publicKeyMode, string $secretKeyMode): string
    {
        if ($publicKeyMode === self::MODE_MISSING && $secretKeyMode === self::MODE_MISSING) {
            return self::MODE_MISSING;
        }

        if ($publicKeyMode === self::MODE_MISSING || $secretKeyMode === self::MODE_MISSING) {
            return self::MODE_MISSING;
        }

        if ($publicKeyMode !== $secretKeyMode) {
            return self::MODE_MIXED_INVALID;
        }

        return $publicKeyMode;
    }

    private function safePrefix(string $key): ?string
    {
        if ($key === '') {
            return null;
        }

        if (preg_match('/^(pk_(?:test|live)_[A-Za-z0-9]{4})/', $key, $matches) === 1) {
            return $matches[1].'…';
        }

        if (preg_match('/^(sk_(?:test|live)_[A-Za-z0-9]{4})/', $key, $matches) === 1) {
            return $matches[1].'…';
        }

        return 'unrecognized…';
    }

    private function webhookUrl(): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base.'/api/v1/payments/paystack/webhook';
    }

    private function webhookRouteExists(): bool
    {
        return collect(Route::getRoutes())->contains(
            fn ($route) => in_array('POST', $route->methods(), true)
                && str_contains($route->uri(), 'payments/paystack/webhook'),
        );
    }

    /**
     * @return list<string>
     */
    private function blockers(
        bool $enabled,
        string $publicKeyMode,
        string $secretKeyMode,
        string $detectedMode,
        bool $configurationComplete,
        string $callbackUrl,
        string $environment,
        string $launchMode,
    ): array {
        $blockers = [];

        if (! $enabled) {
            $blockers[] = 'Paystack integration is disabled (FEATURE_PAYSTACK=false).';
        }

        if ($detectedMode === self::MODE_MISSING) {
            $blockers[] = 'Paystack public or secret key is missing or unrecognized.';
        }

        if ($detectedMode === self::MODE_MIXED_INVALID) {
            $blockers[] = 'Paystack public and secret keys belong to different modes (mixed test/live).';
        }

        if ($callbackUrl === '') {
            $blockers[] = 'PAYSTACK_CALLBACK_URL is not configured.';
        }

        if (! $this->webhookRouteExists()) {
            $blockers[] = 'Paystack webhook route is not registered.';
        }

        if ($environment === 'production' && $detectedMode === self::MODE_TEST) {
            $blockers[] = 'Production environment cannot use Paystack test credentials.';
        }

        if (in_array($launchMode, [LaunchModeService::MODE_LIVE, LaunchModeService::MODE_SOFT_LAUNCH], true)
            && $detectedMode === self::MODE_TEST) {
            $blockers[] = 'Launch mode requires Paystack live credentials.';
        }

        if ($environment === 'staging'
            && $detectedMode === self::MODE_LIVE
            && ! $this->settings->getBool(SystemSettingKeys::STAGING_ALLOW_LIVE_PAYMENTS, false)) {
            $blockers[] = 'Staging environment has live Paystack keys without explicit allowance.';
        }

        if (! $configurationComplete && $blockers === []) {
            $blockers[] = 'Paystack configuration is incomplete.';
        }

        return $blockers;
    }

    /**
     * @return array{aligned: bool, detail: string}
     */
    private function frontendAlignment(string $callbackUrl): array
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        if ($frontendUrl === '') {
            return [
                'aligned' => false,
                'detail' => 'APP_FRONTEND_URL is not configured.',
            ];
        }

        $aligned = $callbackUrl !== '' && str_starts_with($callbackUrl, $frontendUrl);

        return [
            'aligned' => $aligned,
            'detail' => $aligned
                ? 'Callback URL is rooted on the configured frontend domain.'
                : 'Callback URL does not match APP_FRONTEND_URL.',
        ];
    }
}
