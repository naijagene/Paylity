<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerEventType;
use App\Enums\TransactionStatus;
use App\Support\Finance\Money;
use App\Models\FulfillmentAttempt;
use App\Models\Transaction;
use App\Models\TransactionFinancial;

class LedgerPostingService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
        private readonly ProviderCostResolver $providerCostResolver,
        private readonly GatewayFeeResolver $gatewayFeeResolver,
    ) {
    }

    public function postPaymentReceived(Transaction $transaction): ?\App\Models\LedgerTransaction
    {
        if (! $this->isPaymentSuccessful($transaction)) {
            return null;
        }

        if ($this->ledger->hasPosting($transaction->id, LedgerEventType::PAYMENT_RECEIVED)) {
            return null;
        }

        $payable = Money::nairaToKobo((int) $transaction->payable_amount);

        $ledgerTxn = $this->ledger->post(
            $transaction,
            LedgerEventType::PAYMENT_RECEIVED,
            'Customer payment received via Paystack.',
            [
                ['account' => LedgerAccountCode::PAYSTACK_CLEARING, 'type' => 'debit', 'amount_kobo' => $payable],
                ['account' => LedgerAccountCode::CUSTOMER_FUNDS_PENDING, 'type' => 'credit', 'amount_kobo' => $payable],
            ],
            [
                'payable_amount_kobo' => $payable,
                'payment_reference' => $transaction->payment_reference,
            ],
        );

        $gateway = $this->gatewayFeeResolver->snapshot($transaction);

        TransactionFinancial::query()->updateOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'gateway_fee_expected_kobo' => $gateway['expected_kobo'],
                'gateway_fee_actual_kobo' => $gateway['actual_kobo'],
                'settlement_status' => 'pending',
            ],
        );

        $this->postGatewayFeeRecorded($transaction->fresh());

        return $ledgerTxn;
    }

    public function postFulfillmentRecognized(Transaction $transaction, ?FulfillmentAttempt $attempt = null): ?\App\Models\LedgerTransaction
    {
        if ($transaction->status !== TransactionStatus::FULFILLED) {
            return null;
        }

        if ($this->ledger->hasPosting($transaction->id, LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED)) {
            return null;
        }

        if (! $this->ledger->hasPosting($transaction->id, LedgerEventType::PAYMENT_RECEIVED)) {
            $this->postPaymentReceived($transaction);
        }

        $attempt ??= FulfillmentAttempt::query()
            ->where('transaction_id', $transaction->id)
            ->where('status', 'succeeded')
            ->orderByDesc('id')
            ->first();

        $cost = $this->providerCostResolver->resolve($transaction, $attempt);
        $gateway = $this->gatewayFeeResolver->snapshot($transaction);

        $productAmount = Money::nairaToKobo((int) $transaction->product_amount);
        $convenienceFee = Money::nairaToKobo((int) $transaction->convenience_fee);
        $gatewayFeeCharged = Money::nairaToKobo((int) $transaction->gateway_fee);
        $voucherDiscount = Money::nairaToKobo((int) ($transaction->voucher_discount_amount ?? 0));
        $collectedProductAmount = max(0, $productAmount - $voucherDiscount);
        $providerCost = Money::nairaToKobo($cost['provider_cost_naira']);
        $productMargin = max(0, $productAmount - $providerCost);
        $gatewayExpense = $gateway['actual_kobo'] ?? $gateway['expected_kobo'];

        $grossMargin = $this->providerCostResolver->grossMarginKobo(
            $productAmount,
            $providerCost,
            $convenienceFee,
            $gatewayFeeCharged,
            $gatewayExpense,
        );

        $lines = [];

        if ($collectedProductAmount > 0) {
            $lines[] = ['account' => LedgerAccountCode::CUSTOMER_FUNDS_PENDING, 'type' => 'debit', 'amount_kobo' => $collectedProductAmount];
        }

        if ($voucherDiscount > 0) {
            $lines[] = ['account' => LedgerAccountCode::MARKETING_PROMOTION_EXPENSE, 'type' => 'debit', 'amount_kobo' => $voucherDiscount];
        }

        $lines[] = ['account' => LedgerAccountCode::CUSTOMER_FUNDS_PENDING, 'type' => 'debit', 'amount_kobo' => $convenienceFee];
        $lines[] = ['account' => LedgerAccountCode::VTPASS_PRODUCT_COST, 'type' => 'credit', 'amount_kobo' => $providerCost];
        $lines[] = ['account' => LedgerAccountCode::CONVENIENCE_FEE_REVENUE, 'type' => 'credit', 'amount_kobo' => $convenienceFee];

        if ($gatewayFeeCharged > 0) {
            $lines[] = ['account' => LedgerAccountCode::CUSTOMER_FUNDS_PENDING, 'type' => 'debit', 'amount_kobo' => $gatewayFeeCharged];
            $lines[] = ['account' => LedgerAccountCode::GATEWAY_FEE_RECOVERY, 'type' => 'credit', 'amount_kobo' => $gatewayFeeCharged];
        }

        if ($productMargin > 0) {
            $lines[] = ['account' => LedgerAccountCode::PRODUCT_MARGIN_REVENUE, 'type' => 'credit', 'amount_kobo' => $productMargin];
        }

        $ledgerTxn = $this->ledger->post(
            $transaction,
            LedgerEventType::CUSTOMER_FUNDS_RECOGNIZED,
            'Customer funds recognized after fulfillment.',
            $lines,
            [
                'provider_cost_kobo' => $providerCost,
                'provider_cost_source' => $cost['provider_cost_source'],
                'provider_cost_status' => $cost['provider_cost_status'],
                'product_margin_kobo' => $productMargin,
                'gross_margin_kobo' => $grossMargin,
                'voucher_discount_kobo' => $voucherDiscount,
            ],
        );

        TransactionFinancial::query()->updateOrCreate(
            ['transaction_id' => $transaction->id],
            [
                'provider_cost_kobo' => $providerCost,
                'provider_cost_source' => $cost['provider_cost_source'],
                'provider_cost_status' => $cost['provider_cost_status'],
                'gross_margin_kobo' => $grossMargin,
                'gateway_fee_expected_kobo' => $gateway['expected_kobo'],
                'gateway_fee_actual_kobo' => $gateway['actual_kobo'],
            ],
        );

        return $ledgerTxn;
    }

    private function postGatewayFeeRecorded(Transaction $transaction): void
    {
        if ($this->ledger->hasPosting($transaction->id, LedgerEventType::GATEWAY_FEE_RECORDED)) {
            return;
        }

        $gateway = $this->gatewayFeeResolver->snapshot($transaction);
        $expected = $gateway['expected_kobo'];

        if ($expected <= 0) {
            return;
        }

        $this->ledger->post(
            $transaction,
            LedgerEventType::GATEWAY_FEE_RECORDED,
            'Expected Paystack gateway fee recorded.',
            [
                ['account' => LedgerAccountCode::PAYSTACK_GATEWAY_FEE_EXPENSE, 'type' => 'debit', 'amount_kobo' => $expected],
                ['account' => LedgerAccountCode::SETTLEMENT_PAYABLE, 'type' => 'credit', 'amount_kobo' => $expected],
            ],
            [
                'expected_kobo' => $expected,
                'actual_kobo' => $gateway['actual_kobo'],
                'status' => $gateway['status'],
            ],
        );
    }

    private function isPaymentSuccessful(Transaction $transaction): bool
    {
        return in_array($transaction->status, [
            TransactionStatus::PAYMENT_SUCCESS,
            TransactionStatus::FULFILLMENT_PENDING,
            TransactionStatus::FULFILLED,
            TransactionStatus::FAILED,
        ], true);
    }
}
