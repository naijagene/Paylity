export const PRODUCT_LABELS: Record<string, string> = {
  airtime: "Airtime",
  data: "Data",
  electricity: "Electricity",
};

export type StatusBadgeVariant = "success" | "pending" | "failed";

export type TimelinePhase =
  | "awaiting"
  | "processing"
  | "delivered"
  | "delivery_failed"
  | "payment_failed";

export function getPaymentBadgeVariant(status: string): StatusBadgeVariant {
  if (status === "payment_success" || status === "fulfilled") {
    return "success";
  }

  if (
    status === "payment_pending" ||
    status === "fulfillment_pending" ||
    status === "created"
  ) {
    return "pending";
  }

  return "failed";
}

export function getFulfillmentBadgeVariant(status: string): StatusBadgeVariant {
  if (status === "fulfilled") {
    return "success";
  }

  if (status === "payment_success" || status === "fulfillment_pending") {
    return "pending";
  }

  if (status === "failed" || status === "cancelled") {
    return "failed";
  }

  return "pending";
}

export function getPaymentBadgeLabel(status: string): string {
  switch (status) {
    case "payment_success":
    case "fulfilled":
      return "Payment Successful";
    case "payment_failed":
      return "Payment Failed";
    case "payment_pending":
      return "Payment Pending";
    default:
      return "Payment Pending";
  }
}

export function getFulfillmentBadgeLabel(status: string): string {
  switch (status) {
    case "fulfilled":
      return "Delivered";
    case "fulfillment_pending":
      return "Processing Order";
    case "payment_success":
      return "Awaiting Delivery";
    case "failed":
      return "Delivery Failed";
    case "cancelled":
      return "Cancelled";
    default:
      return "Awaiting Delivery";
  }
}

export function getTimelinePhase(status: string): TimelinePhase {
  switch (status) {
    case "fulfilled":
      return "delivered";
    case "fulfillment_pending":
      return "processing";
    case "failed":
      return "delivery_failed";
    case "payment_failed":
      return "payment_failed";
    default:
      return "awaiting";
  }
}

export function shouldPollTransactionStatus(status: string): boolean {
  return status === "payment_success" || status === "fulfillment_pending";
}

export function isTerminalTransactionStatus(status: string): boolean {
  return ["fulfilled", "failed", "cancelled", "payment_failed"].includes(
    status,
  );
}
