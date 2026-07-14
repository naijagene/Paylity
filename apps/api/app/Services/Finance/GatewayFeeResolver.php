<?php

namespace App\Services\Finance;

use App\Models\Transaction;
use App\Services\Finance\PaystackGatewayFeeCalculator;
use App\Support\Finance\Money;

class GatewayFeeResolver
{
    public function __construct(
        private readonly PaystackGatewayFeeCalculator $paystackGatewayFeeCalculator,
    ) {
    }

    public function expectedFeeKobo(Transaction $transaction): int
    {
        return $this->paystackGatewayFeeCalculator->feeKoboForPayable(
            Money::nairaToKobo((int) $transaction->payable_amount),
        );
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
