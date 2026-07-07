import type { TransactionReceipt } from "@/lib/api/transactions";
import type { CheckoutFields, ProductType } from "@/lib/checkout/types";
import { PRODUCT_LABELS } from "@/lib/transaction/display";

const NETWORK_LABELS: Record<string, string> = {
  mtn: "MTN",
  airtel: "Airtel",
  glo: "Glo",
  "9mobile": "9Mobile",
  etisalat: "9Mobile",
};

export function formatNetworkLabel(network: string): string {
  const normalized = network.trim().toLowerCase();
  return NETWORK_LABELS[normalized] ?? network.toUpperCase();
}

export function buildCheckoutProductDisplayName(
  product: ProductType,
  fields: CheckoutFields,
  dataPlanName?: string,
): string {
  if (product === "airtime") {
    return fields.network
      ? `${formatNetworkLabel(fields.network)} Airtime`
      : "Airtime";
  }

  if (product === "data") {
    const planName = dataPlanName || fields.dataPlan;
    if (fields.network && planName) {
      return `${formatNetworkLabel(fields.network)} ${planName}`;
    }
    return planName || "Data";
  }

  if (product === "electricity") {
    if (!fields.disco) {
      return "Electricity";
    }

    const meterType = fields.meterType
      ? fields.meterType.charAt(0).toUpperCase() + fields.meterType.slice(1)
      : "";

    return meterType
      ? `${fields.disco.toUpperCase()} ${meterType} Electricity`
      : `${fields.disco.toUpperCase()} Electricity`;
  }

  return product;
}

export function getReceiptProductLabel(
  receipt?: TransactionReceipt | null,
  productType?: string,
): string {
  if (receipt?.product_display_name) {
    return receipt.product_display_name;
  }

  if (receipt?.product_label) {
    return receipt.product_label;
  }

  if (productType) {
    return PRODUCT_LABELS[productType] ?? productType;
  }

  return "—";
}

export function getReceiptPhoneDisplay(receipt?: TransactionReceipt | null): string {
  return receipt?.phone_display ?? receipt?.customer_phone_masked ?? "—";
}

export function formatReceiptTimestamp(
  timestamp?: string | null,
  timestampDisplay?: string | null,
): string | null {
  if (timestampDisplay) {
    return timestampDisplay;
  }

  if (!timestamp) {
    return null;
  }

  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
    timeZone: "Africa/Lagos",
  })
    .format(new Date(timestamp))
    .replace(",", ",") + " WAT";
}

export function getCheckoutRecipientLabel(
  product: ProductType,
  fields: CheckoutFields,
): { label: string; value: string } {
  if (product === "electricity") {
    return {
      label: "Meter number",
      value: fields.meterNumber || "—",
    };
  }

  const phone = fields.useMyNumber ? fields.customerPhone : fields.recipientPhone;

  return {
    label: "Recipient",
    value: phone || "—",
  };
}
