<?php

namespace App\Enums;

final class LedgerEventType
{
    public const PAYMENT_RECEIVED = 'payment_received';

    public const GATEWAY_FEE_RECORDED = 'gateway_fee_recorded';

    public const CUSTOMER_FUNDS_RECOGNIZED = 'customer_funds_recognized';

    public const FULFILLMENT_COST_RECORDED = 'fulfillment_cost_recorded';

    public const CONVENIENCE_FEE_RECOGNIZED = 'convenience_fee_recognized';

    public const PRODUCT_MARGIN_RECOGNIZED = 'product_margin_recognized';

    public const SETTLEMENT_EXPECTED = 'settlement_expected';

    public const SETTLEMENT_RECEIVED = 'settlement_received';

    public const SETTLEMENT_DIFFERENCE_RECORDED = 'settlement_difference_recorded';

    public const PROVIDER_WALLET_DEBIT_RECORDED = 'provider_wallet_debit_recorded';

    public const MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const REVERSAL = 'reversal';
}
