<?php

namespace Database\Seeders;

use App\Enums\LedgerAccountCode;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

class LedgerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [LedgerAccountCode::PAYSTACK_CLEARING, 'Paystack Clearing', 'asset', 'Customer collections awaiting Paystack settlement.'],
            [LedgerAccountCode::VTPASS_WALLET_ASSET, 'VTPass Wallet Asset', 'asset', 'Provider wallet balance consumed for fulfillment.'],
            [LedgerAccountCode::CASH_ADJUSTMENT, 'Cash Adjustment', 'asset', 'Bank settlement and manual cash adjustments.'],
            [LedgerAccountCode::CUSTOMER_FUNDS_PENDING, 'Customer Funds Pending', 'liability', 'Collected customer funds pending fulfillment recognition.'],
            [LedgerAccountCode::PROVIDER_PAYABLE, 'Provider Payable', 'liability', 'Amount owed to fulfillment providers.'],
            [LedgerAccountCode::SETTLEMENT_PAYABLE, 'Settlement Payable', 'liability', 'Expected settlement obligations.'],
            [LedgerAccountCode::CONVENIENCE_FEE_REVENUE, 'Convenience Fee Revenue', 'revenue', 'PAYLITY convenience fee income.'],
            [LedgerAccountCode::GATEWAY_FEE_RECOVERY, 'Gateway Fee Recovery', 'revenue', 'Gateway fees recovered from customers.'],
            [LedgerAccountCode::PRODUCT_MARGIN_REVENUE, 'Product Margin Revenue', 'revenue', 'Margin between product price and provider cost.'],
            [LedgerAccountCode::PAYSTACK_GATEWAY_FEE_EXPENSE, 'Paystack Gateway Fee Expense', 'expense', 'Actual Paystack processing fees.'],
            [LedgerAccountCode::VTPASS_PRODUCT_COST, 'VTPass Product Cost', 'expense', 'Provider product fulfillment cost.'],
            [LedgerAccountCode::RECONCILIATION_ADJUSTMENT_EXPENSE, 'Reconciliation Adjustment Expense', 'expense', 'Financial reconciliation adjustments.'],
            [LedgerAccountCode::SUSPENSE, 'Suspense', 'control', 'Unallocated financial differences pending review.'],
            [LedgerAccountCode::SETTLEMENT_DIFFERENCE, 'Settlement Difference', 'control', 'Paystack settlement variance tracking.'],
        ];

        foreach ($accounts as [$code, $name, $category, $description]) {
            LedgerAccount::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category' => $category,
                    'description' => $description,
                    'is_active' => true,
                ],
            );
        }
    }
}
