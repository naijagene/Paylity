<?php

namespace App\Enums;

final class LedgerAccountCode
{
    public const PAYSTACK_CLEARING = 'paystack_clearing';

    public const VTPASS_WALLET_ASSET = 'vtpass_wallet_asset';

    public const CASH_ADJUSTMENT = 'cash_adjustment';

    public const CUSTOMER_FUNDS_PENDING = 'customer_funds_pending';

    public const PROVIDER_PAYABLE = 'provider_payable';

    public const SETTLEMENT_PAYABLE = 'settlement_payable';

    public const CONVENIENCE_FEE_REVENUE = 'convenience_fee_revenue';

    public const GATEWAY_FEE_RECOVERY = 'gateway_fee_recovery';

    public const PRODUCT_MARGIN_REVENUE = 'product_margin_revenue';

    public const PAYSTACK_GATEWAY_FEE_EXPENSE = 'paystack_gateway_fee_expense';

    public const VTPASS_PRODUCT_COST = 'vtpass_product_cost';

    public const RECONCILIATION_ADJUSTMENT_EXPENSE = 'reconciliation_adjustment_expense';

    public const MARKETING_PROMOTION_EXPENSE = 'marketing_promotion_expense';

    public const SUSPENSE = 'suspense';

    public const SETTLEMENT_DIFFERENCE = 'settlement_difference';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PAYSTACK_CLEARING,
            self::VTPASS_WALLET_ASSET,
            self::CASH_ADJUSTMENT,
            self::CUSTOMER_FUNDS_PENDING,
            self::PROVIDER_PAYABLE,
            self::SETTLEMENT_PAYABLE,
            self::CONVENIENCE_FEE_REVENUE,
            self::GATEWAY_FEE_RECOVERY,
            self::PRODUCT_MARGIN_REVENUE,
            self::PAYSTACK_GATEWAY_FEE_EXPENSE,
            self::VTPASS_PRODUCT_COST,
            self::RECONCILIATION_ADJUSTMENT_EXPENSE,
            self::MARKETING_PROMOTION_EXPENSE,
            self::SUSPENSE,
            self::SETTLEMENT_DIFFERENCE,
        ];
    }
}
