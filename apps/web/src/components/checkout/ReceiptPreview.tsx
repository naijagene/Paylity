import { getProductSchema } from "@/lib/checkout/checkoutSchemas";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { formatGatewayFeeLabel } from "@/lib/checkout/pricing";
import type { CheckoutFields, ProductType } from "@/lib/checkout/types";

type ReceiptPreviewProps = {
  product: ProductType;
  fields: CheckoutFields;
  productAmount: number;
  convenienceFee: number;
  gatewayFee: number;
  payableAmount: number;
  transactionReference: string | null;
};

export function ReceiptPreview({
  product,
  fields,
  productAmount,
  convenienceFee,
  gatewayFee,
  payableAmount,
  transactionReference,
}: ReceiptPreviewProps) {
  const schema = getProductSchema(product);
  const gatewayFeeLabel = formatGatewayFeeLabel(gatewayFee);
  const timestamp = new Date().toLocaleString("en-NG", {
    dateStyle: "medium",
    timeStyle: "short",
  });

  const rows = [
    { label: "Transaction Reference", value: transactionReference ?? "Pending" },
    { label: "Product", value: schema.label },
    { label: "Customer Phone", value: fields.customerPhone || "—" },
    { label: "Product Amount", value: formatNaira(productAmount) },
    { label: "Convenience Fee", value: formatNaira(convenienceFee) },
    { label: "Gateway Charge", value: gatewayFeeLabel },
    { label: "Total Paid", value: formatNaira(payableAmount) },
    { label: "Status", value: "Awaiting payment" },
    { label: "Timestamp", value: timestamp },
  ];

  return (
    <div className="rounded-3xl border border-dark/5 bg-white p-5 shadow-sm sm:p-6">
      <p className="text-xs font-semibold uppercase tracking-wide text-foreground/50">
        Receipt preview
      </p>
      <p className="mt-2 text-lg font-black text-foreground">
        PAYLITY <span className="text-primary">NG</span>
      </p>

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
