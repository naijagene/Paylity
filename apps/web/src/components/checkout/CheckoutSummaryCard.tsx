import { formatNaira } from "@/lib/checkout/formatNaira";
import { formatGatewayFeeLabel } from "@/lib/checkout/pricing";
import { maskPhone } from "@/lib/checkout/normalizePhone";
import {
  buildCheckoutProductDisplayName,
  formatNetworkLabel,
  getCheckoutRecipientLabel,
} from "@/lib/receipt/display";
import type { CheckoutFields, ProductType } from "@/lib/checkout/types";
import { GuestLimitBanner } from "./GuestLimitBanner";
import { ReceiptPreview } from "./ReceiptPreview";
import { TrustIndicators } from "./TrustIndicators";

type SummaryItem = {
  label: string;
  value: string;
};

type PricingMode = "estimated" | "confirmed";

type CheckoutSummaryCardProps = {
  product: ProductType;
  fields: CheckoutFields;
  productAmount: number;
  convenienceFee: number;
  gatewayFee: number;
  payableAmount: number;
  transactionReference: string | null;
  pricingMode: PricingMode;
  transactionReady: boolean;
  isOverGuestLimit: boolean;
  dataPlanName?: string;
  onReduceProductAmount?: () => void;
};

function buildSummaryItems(
  product: ProductType,
  fields: CheckoutFields,
  dataPlanName?: string,
): SummaryItem[] {
  const productName = buildCheckoutProductDisplayName(
    product,
    fields,
    dataPlanName,
  );
  const items: SummaryItem[] = [{ label: "Product", value: productName }];
  const recipient = getCheckoutRecipientLabel(product, fields);

  if (product === "airtime" || product === "data") {
    items.push({
      label: "Network",
      value: fields.network ? formatNetworkLabel(fields.network) : "—",
    });
    items.push({
      label: recipient.label,
      value: maskPhone(recipient.value) || recipient.value || "—",
    });
  }

  if (product === "electricity") {
    items.push({
      label: recipient.label,
      value: recipient.value,
    });

    if (fields.customerName) {
      items.push({ label: "Customer name", value: fields.customerName });
    }
  }

  return items;
}

export function CheckoutSummaryCard({
  product,
  fields,
  productAmount,
  convenienceFee,
  gatewayFee,
  payableAmount,
  transactionReference,
  pricingMode,
  transactionReady,
  isOverGuestLimit,
  dataPlanName,
  onReduceProductAmount,
}: CheckoutSummaryCardProps) {
  const items = buildSummaryItems(product, fields, dataPlanName);
  const gatewayFeeLabel =
    pricingMode === "confirmed"
      ? formatNaira(gatewayFee)
      : formatGatewayFeeLabel(gatewayFee);
  const pricingLabel = pricingMode === "confirmed" ? "Confirmed" : "Estimated";

  return (
    <div className="space-y-4">
      {transactionReady ? (
        <div className="rounded-3xl border border-success/20 bg-success/5 p-5 sm:p-6">
          <p className="text-sm font-bold text-success">Transaction Ready</p>
          <p className="mt-2 text-sm text-foreground/70">
            Your checkout has been initialized successfully.
          </p>
          {transactionReference ? (
            <p className="mt-3 font-mono text-sm font-semibold text-foreground">
              {transactionReference}
            </p>
          ) : null}
        </div>
      ) : null}

      <div className="rounded-3xl border border-dark/5 bg-white p-5 shadow-sm sm:p-6">
        <div className="mb-5 flex items-center justify-between gap-3">
          <h2 className="text-lg font-bold text-foreground">Review your payment</h2>
          <span className="rounded-full bg-primary/15 px-3 py-1 text-xs font-semibold text-dark">
            {pricingLabel}
          </span>
        </div>

        <dl className="space-y-4">
          {items.map((item) => (
            <div
              key={item.label}
              className="flex items-start justify-between gap-4 border-b border-dark/5 pb-4"
            >
              <dt className="text-sm text-foreground/60">{item.label}</dt>
              <dd className="max-w-[62%] text-right text-sm font-semibold text-foreground">
                {item.value}
              </dd>
            </div>
          ))}

          <div className="flex items-start justify-between gap-4 border-b border-dark/5 pb-4">
            <dt className="text-sm text-foreground/60">Product Amount</dt>
            <dd className="text-right text-sm font-semibold text-foreground">
              {formatNaira(productAmount)}
            </dd>
          </div>

          <div className="flex items-start justify-between gap-4 border-b border-dark/5 pb-4">
            <dt className="text-sm text-foreground/60">Convenience Fee</dt>
            <dd className="text-right text-sm font-semibold text-foreground">
              {formatNaira(convenienceFee)}
            </dd>
          </div>

          <div className="flex items-start justify-between gap-4 border-b border-dark/5 pb-4">
            <dt className="text-sm text-foreground/60">Payment Processing Fee</dt>
            <dd className="max-w-[62%] text-right text-sm font-semibold text-foreground">
              {gatewayFeeLabel}
            </dd>
          </div>

          <div className="flex items-start justify-between gap-4 pt-1">
            <dt className="text-base font-bold text-foreground">Total Payable</dt>
            <dd className="text-right text-base font-black text-foreground">
              {formatNaira(payableAmount)}
            </dd>
          </div>
        </dl>

        {isOverGuestLimit ? (
          <div className="mt-5">
            <GuestLimitBanner onReduceProductAmount={onReduceProductAmount} />
          </div>
        ) : null}

        <TrustIndicators className="mt-6" />
      </div>

      <ReceiptPreview
        product={product}
        fields={fields}
        productAmount={productAmount}
        convenienceFee={convenienceFee}
        gatewayFee={gatewayFee}
        payableAmount={payableAmount}
        transactionReference={transactionReference}
        pricingMode={pricingMode}
        dataPlanName={dataPlanName}
        status={transactionReady ? "Transaction ready" : "Awaiting payment"}
      />
    </div>
  );
}
