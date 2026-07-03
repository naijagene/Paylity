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
    case "payment_success":
      return "processing";
    case "failed":
      return "delivery_failed";
    case "payment_failed":
      return "payment_failed";
    default:
      return "awaiting";
  }
}

export type CallbackPageTone = "success" | "pending" | "failed" | "delivery_failed";

export type CallbackPageHeading = {
  title: string;
  subtitle: string;
  detail?: string;
  tone: CallbackPageTone;
  showSpinner: boolean;
};

export function getCallbackPageHeading(status: string): CallbackPageHeading {
  switch (status) {
    case "fulfilled":
      return {
        title: "Payment Completed Successfully",
        subtitle: "Your order has been delivered.",
        tone: "success",
        showSpinner: false,
      };
    case "payment_success":
    case "fulfillment_pending":
      return {
        title: "Payment Completed Successfully",
        subtitle: "Delivery is being processed.",
        detail: "This usually takes 30 seconds to 2 minutes.",
        tone: "success",
        showSpinner: false,
      };
    case "failed":
      return {
        title: "Payment Successful, Delivery Failed",
        subtitle:
          "Your payment was confirmed, but delivery could not be completed.",
        tone: "delivery_failed",
        showSpinner: false,
      };
    case "payment_failed":
      return {
        title: "Payment Failed",
        subtitle: "Your payment could not be completed.",
        tone: "failed",
        showSpinner: false,
      };
    case "payment_pending":
    case "created":
      return {
        title: "Payment Pending",
        subtitle: "Your payment is still being confirmed.",
        tone: "pending",
        showSpinner: true,
      };
    default:
      return {
        title: "Payment Pending",
        subtitle: "Your payment is still being confirmed.",
        tone: "pending",
        showSpinner: true,
      };
  }
}

export function shouldRenderCallbackSuccessView(status: string): boolean {
  return (
    status === "fulfilled" ||
    status === "payment_success" ||
    status === "fulfillment_pending" ||
    status === "failed"
  );
}

export function shouldRenderCallbackPendingView(status: string): boolean {
  return status === "payment_pending" || status === "created";
}

export function shouldPollTransactionStatus(status: string): boolean {
  return status === "payment_success" || status === "fulfillment_pending";
}

export function isAwaitingDelivery(status: string): boolean {
  return status === "payment_success" || status === "fulfillment_pending";
}

export function isTerminalTransactionStatus(status: string): boolean {
  return ["fulfilled", "failed", "cancelled", "payment_failed"].includes(
    status,
  );
}
