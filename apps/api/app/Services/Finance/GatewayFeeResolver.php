<?php

namespace App\Services\Finance;

use App\Support\Finance\Money;
use App\Models\Transaction;
use App\Services\Platform\SystemSettingsService;
use App\Support\Platform\SystemSettingKeys;

class GatewayFeeResolver
{
    public function __construct(
        private readonly SystemSettingsService $systemSettings,
    ) {
    }

    public function expectedFeeKobo(Transaction $transaction): int
    {
        $basisPoints = max(0, $this->systemSettings->getInt(
            SystemSettingKeys::FINANCIAL_PAYSTACK_FEE_BASIS_POINTS,
            150,
        ));
        $flatFee = max(0, $this->systemSettings->getInt(
            SystemSettingKeys::FINANCIAL_PAYSTACK_FEE_FLAT_KOBO,
            10000,
        ));

        $payableKobo = Money::nairaToKobo((int) $transaction->payable_amount);
        $percentageFee = (int) round($payableKobo * ($basisPoints / 10000));

        return $percentageFee + $flatFee;
    }

    public function actualFeeKobo(Transaction $transaction): ?int
    {
        $fee = data_get($transaction->response_payload, 'verify.fees')
            ?? data_get($transaction->response_payload, 'verify.fee')
            ?? data_get($transaction->response_payload, 'webhook.fees');

        if (is_numeric($fee) && (int) $fee >= 0) {
            return (int) $fee;
        }

        return null;
    }

    /**
     * @return array{expected_kobo: int, actual_kobo: int|null, status: string}
     */
    public function snapshot(Transaction $transaction): array
    {
        $expected = $this->expectedFeeKobo($transaction);
        $actual = $this->actualFeeKobo($transaction);

        return [
            'expected_kobo' => $expected,
            'actual_kobo' => $actual,
            'status' => $actual !== null ? 'actual' : 'provisional',
        ];
    }
}
