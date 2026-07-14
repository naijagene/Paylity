<?php

namespace App\Services\Launch;

use App\Enums\TransactionStatus;
use App\Exceptions\FraudCheckException;
use App\Models\Transaction;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;
use Illuminate\Support\Carbon;

class LaunchModeService
{
    public const MODE_STAGING = 'staging';

    public const MODE_SOFT_LAUNCH = 'soft_launch';

    public const MODE_LIVE = 'live';

    public const MODE_MAINTENANCE = 'maintenance';

    public function __construct(
        private readonly SystemSettingsService $settings,
    ) {
    }

    public function mode(): string
    {
        return $this->settings->getString(SystemSettingKeys::LAUNCH_MODE, self::MODE_STAGING);
    }

    /**
     * @throws FraudCheckException
     */
    public function assertCheckoutAllowed(string $productType, int $payableAmount): void
    {
        if ($this->mode() === self::MODE_MAINTENANCE) {
            throw new FraudCheckException(
                $this->settings->getString(
                    SystemSettingKeys::LAUNCH_INCIDENT_MESSAGE,
                    'PAYLITY is temporarily unavailable for checkout.',
                ),
                'LAUNCH_MAINTENANCE',
            );
        }

        $allowedProducts = $this->allowedProducts();

        if ($allowedProducts !== [] && ! in_array($productType, $allowedProducts, true)) {
            throw new FraudCheckException(
                'This product is not available during the current launch phase.',
                'LAUNCH_PRODUCT_BLOCKED',
            );
        }

        if (! in_array($this->mode(), [self::MODE_SOFT_LAUNCH, self::MODE_LIVE], true)) {
            return;
        }

        $usage = $this->dailyUsage();

        $transactionLimit = $this->settings->getInt(SystemSettingKeys::LAUNCH_TRANSACTION_LIMIT_DAILY, 0);
        if ($transactionLimit > 0 && $usage['transaction_count'] >= $transactionLimit) {
            throw new FraudCheckException(
                'PAYLITY has reached today\'s launch transaction limit. Please try again tomorrow.',
                'LAUNCH_TRANSACTION_CAP',
            );
        }

        $revenueLimit = $this->settings->getInt(SystemSettingKeys::LAUNCH_REVENUE_LIMIT_DAILY, 0);
        if ($revenueLimit > 0 && ($usage['gross_collection_naira'] + $payableAmount) > $revenueLimit) {
            throw new FraudCheckException(
                'PAYLITY has reached today\'s launch collection limit. Please try again tomorrow.',
                'LAUNCH_REVENUE_CAP',
            );
        }
    }

    /**
     * @return list<string>
     */
    public function allowedProducts(): array
    {
        $raw = $this->settings->get(SystemSettingKeys::LAUNCH_ALLOWED_PRODUCTS, 'airtime,data,electricity');

        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }

    /**
     * @return array{
     *     transaction_count: int,
     *     gross_collection_naira: int,
     *     transaction_limit_daily: int,
     *     revenue_limit_daily: int,
     *     transaction_utilization_pct: float|null,
     *     revenue_utilization_pct: float|null
     * }
     */
    public function dailyUsage(): array
    {
        $from = today()->startOfDay();
        $to = today()->endOfDay();
        $successfulStatuses = [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
            TransactionStatus::FAILED,
        ];

        $query = Transaction::query()->whereBetween('created_at', [$from, $to]);
        $transactionCount = (int) (clone $query)
            ->whereIn('status', $successfulStatuses)
            ->count();
        $grossCollection = (int) (clone $query)
            ->whereIn('status', $successfulStatuses)
            ->sum('payable_amount');

        $transactionLimit = $this->settings->getInt(SystemSettingKeys::LAUNCH_TRANSACTION_LIMIT_DAILY, 0);
        $revenueLimit = $this->settings->getInt(SystemSettingKeys::LAUNCH_REVENUE_LIMIT_DAILY, 0);

        return [
            'transaction_count' => $transactionCount,
            'gross_collection_naira' => $grossCollection,
            'transaction_limit_daily' => $transactionLimit,
            'revenue_limit_daily' => $revenueLimit,
            'transaction_utilization_pct' => $transactionLimit > 0
                ? round(($transactionCount / $transactionLimit) * 100, 2)
                : null,
            'revenue_utilization_pct' => $revenueLimit > 0
                ? round(($grossCollection / $revenueLimit) * 100, 2)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setMode(string $mode): array
    {
        $valid = [
            self::MODE_STAGING,
            self::MODE_SOFT_LAUNCH,
            self::MODE_LIVE,
            self::MODE_MAINTENANCE,
        ];

        if (! in_array($mode, $valid, true)) {
            throw new \InvalidArgumentException("Invalid launch mode: {$mode}");
        }

        $this->settings->set(SystemSettingKeys::LAUNCH_MODE, $mode);

        if (in_array($mode, [self::MODE_SOFT_LAUNCH, self::MODE_LIVE], true)) {
            $startedAt = $this->settings->getString(SystemSettingKeys::LAUNCH_STARTED_AT);
            if ($startedAt === '') {
                $this->settings->set(SystemSettingKeys::LAUNCH_STARTED_AT, now()->toIso8601String());
            }
        }

        return $this->snapshot();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'mode' => $this->mode(),
            'started_at' => $this->settings->getString(SystemSettingKeys::LAUNCH_STARTED_AT) ?: null,
            'allowed_products' => $this->allowedProducts(),
            'support_phone' => $this->settings->getString(SystemSettingKeys::LAUNCH_SUPPORT_PHONE) ?: null,
            'support_email' => $this->settings->getString(SystemSettingKeys::LAUNCH_SUPPORT_EMAIL) ?: null,
            'incident_message' => $this->settings->getString(SystemSettingKeys::LAUNCH_INCIDENT_MESSAGE) ?: null,
            'daily_usage' => $this->dailyUsage(),
        ];
    }
}
