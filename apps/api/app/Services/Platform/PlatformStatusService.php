<?php

namespace App\Services\Platform;

use App\Support\Platform\SystemSettingKeys;

class PlatformStatusService
{
    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    /**
     * @return array{
     *     checkout_enabled: bool,
     *     maintenance_mode: bool,
     *     incident_mode: bool,
     *     message: string|null
     * }
     */
    public function publicStatus(): array
    {
        $maintenanceMode = $this->settings->getBool(SystemSettingKeys::MAINTENANCE_MODE);
        $incidentMode = $this->settings->getBool(SystemSettingKeys::INCIDENT_MODE);
        $guestCheckoutEnabled = $this->settings->getBool(SystemSettingKeys::GUEST_CHECKOUT_ENABLED, true);

        $checkoutEnabled = $guestCheckoutEnabled && ! $maintenanceMode && ! $incidentMode;

        $message = match (true) {
            $incidentMode => 'PAYLITY is experiencing an incident. Checkout is temporarily paused.',
            $maintenanceMode => 'PAYLITY is temporarily unavailable for checkout.',
            ! $guestCheckoutEnabled => 'Guest checkout is currently unavailable.',
            default => null,
        };

        return [
            'checkout_enabled' => $checkoutEnabled,
            'maintenance_mode' => $maintenanceMode,
            'incident_mode' => $incidentMode,
            'message' => $message,
        ];
    }
}
