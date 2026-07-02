import { DATA_PLANS } from "@/lib/checkout/constants";
import { getProductSchema } from "@/lib/checkout/checkoutSchemas";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { maskPhone } from "@/lib/checkout/normalizePhone";
import type { CheckoutFields, ProductType } from "@/lib/checkout/types";
import { GuestLimitBanner } from "./GuestLimitBanner";

type SummaryItem = {
  label: string;
  value: string;
};

type CheckoutSummaryCardProps = {
  product: ProductType;
  fields: CheckoutFields;
  amount: number;
  fee: number;
  total: number;
  isOverGuestLimit: boolean;
  onReduceAmount?: () => void;
};

function buildSummaryItems(
  product: ProductType,
  fields: CheckoutFields,
  amount: number,
): SummaryItem[] {
  const schema = getProductSchema(product);
  const items: SummaryItem[] = [{ label: "Product", value: schema.label }];

  if (product === "airtime" || product === "data") {
    items.push({ label: "Network", value: fields.network || "—" });
    const phone = fields.useMyNumber ? fields.customerPhone : fields.recipientPhone;
    items.push({ label: "Recipient", value: maskPhone(phone) || "—" });
  }

  if (product === "data") {
    const plan = DATA_PLANS.find((item) => item.id === fields.dataPlan);
    if (plan) {
      items.push({ label: "Plan", value: plan.name });
      items.push({ label: "Validity", value: plan.validity });
    }
  }

  if (product === "electricity") {
    items.push({ label: "Provider", value: fields.disco || "—" });
    items.push({
      label: "Meter type",
      value: fields.meterType.charAt(0).toUpperCase() + fields.meterType.slice(1),
    });
    items.push({ label: "Meter number", value: fields.meterNumber || "—" });
    items.push({ label: "Customer name", value: fields.customerName || "—" });
  }

  items.push({ label: "Amount", value: formatNaira(amount) });

  return items;
}

export function CheckoutSummaryCard({
  product,
  fields,
  amount,
  fee,
  total,
  isOverGuestLimit,
  onReduceAmount,
}: CheckoutSummaryCardProps) {
  const items = buildSummaryItems(product, fields, amount);

  return (
    <div className="rounded-3xl border border-dark/5 bg-white p-5 shadow-sm sm:p-6">
      <h2 className="mb-4 text-lg font-bold text-foreground">Review your payment</h2>

      <dl className="space-y-3">
        {items.map((item) => (
          <div
            key={item.label}
            className="flex items-start justify-between gap-4 border-b border-dark/5 pb-3 last:border-b-0 last:pb-0"
          >
            <dt className="text-sm text-foreground/60">{item.label}</dt>
            <dd className="text-right text-sm font-semibold text-foreground">
              {item.value}
            </dd>
          </div>
        ))}

        <div className="flex items-start justify-between gap-4 border-b border-dark/5 pb-3">
          <dt className="text-sm text-foreground/60">Fee</dt>
          <dd className="text-right text-sm font-semibold text-foreground">
            {formatNaira(fee)}
          </dd>
        </div>

        <div className="flex items-start justify-between gap-4 pt-1">
          <dt className="text-base font-bold text-foreground">Total</dt>
          <dd className="text-right text-base font-black text-foreground">
            {formatNaira(total)}
          </dd>
        </div>
      </dl>

      {isOverGuestLimit ? (
        <div className="mt-5">
          <GuestLimitBanner onReduceAmount={onReduceAmount} />
        </div>
      ) : null}

      <div className="mt-5 flex items-center justify-center gap-4 rounded-2xl bg-dark/[0.03] px-4 py-3 text-xs font-semibold text-foreground/70 sm:text-sm">
        <span>🔒 Secure payment</span>
        <span>·</span>
        <span>⚡ Instant delivery</span>
      </div>
    </div>
  );
}
