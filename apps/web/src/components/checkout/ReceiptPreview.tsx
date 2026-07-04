import { formatNaira } from "@/lib/checkout/formatNaira";
import { formatGatewayFeeLabel } from "@/lib/checkout/pricing";
import { maskPhone } from "@/lib/checkout/normalizePhone";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import {
  buildCheckoutProductDisplayName,
  getCheckoutRecipientLabel,
} from "@/lib/receipt/display";
import type { CheckoutFields, ProductType } from "@/lib/checkout/types";

type ReceiptPreviewProps = {
  product: ProductType;
  fields: CheckoutFields;
  productAmount: number;
  convenienceFee: number;
  gatewayFee: number;
  payableAmount: number;
  transactionReference: string | null;
  pricingMode?: "estimated" | "confirmed";
  dataPlanName?: string;
  status?: string;
};

export function ReceiptPreview({
  product,
  fields,
  productAmount,
  convenienceFee,
  gatewayFee,
  payableAmount,
  transactionReference,
  pricingMode = "estimated",
  dataPlanName,
  status = "Awaiting payment",
}: ReceiptPreviewProps) {
  const productLabel = buildCheckoutProductDisplayName(product, fields, dataPlanName);
  const recipient = getCheckoutRecipientLabel(product, fields);
  const recipientValue =
    product === "electricity"
      ? recipient.value
      : maskPhone(recipient.value) || recipient.value || "—";
  const gatewayFeeLabel =
    pricingMode === "confirmed"
      ? formatNaira(gatewayFee)
      : formatGatewayFeeLabel(gatewayFee);
  const timestamp = new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
    timeZone: "Africa/Lagos",
  }).format(new Date()) + " WAT";

  const rows = [
    { label: "Transaction Reference", value: transactionReference ?? "Pending" },
    { label: "Product", value: productLabel },
    { label: recipient.label, value: recipientValue },
    ...(fields.customerEmail
      ? [{ label: "Email", value: fields.customerEmail }]
      : []),
    { label: "Product Amount", value: formatNaira(productAmount) },
    { label: "Convenience Fee", value: formatNaira(convenienceFee) },
    { label: "Gateway Charge", value: gatewayFeeLabel },
    { label: "Total Paid", value: formatNaira(payableAmount) },
    { label: "Status", value: status },
    { label: "Timestamp", value: timestamp },
  ];

  return (
    <div className="rounded-3xl border border-dark/5 bg-white p-5 shadow-sm sm:p-6">
      <p className="text-xs font-semibold uppercase tracking-wide text-foreground/50">
        Receipt preview
      </p>
      <PaylityLogo size="sm" className="mt-2" />

      <dl className="mt-4 space-y-3">
        {rows.map((row) => (
          <div
            key={row.label}
            className="flex items-start justify-between gap-4 border-b border-dark/5 pb-3 last:border-b-0 last:pb-0"
          >
            <dt className="text-sm text-foreground/60">{row.label}</dt>
            <dd
              className={`max-w-[60%] text-right text-sm font-semibold text-foreground ${
                row.label === "Transaction Reference" ? "font-mono text-xs" : ""
              }`}
            >
              {row.value}
            </dd>
          </div>
        ))}
      </dl>
    </div>
  );
}
