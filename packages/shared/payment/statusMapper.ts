export type NormalizedTransactionStatus =
  | "created"
  | "payment_pending"
  | "payment_failed"
  | "payment_success"
  | "fulfillment_pending"
  | "fulfilled"
  | "failed";

export type TransactionLike = {
  status?: string | null;
};

export type StatusBadgeVariant = "success" | "pending" | "failed";

export type TimelinePhase =
  | "awaiting"
  | "processing"
  | "delivered"
  | "delivery_failed"
  | "payment_failed";

export type HeroTone = "success" | "pending" | "failed" | "delivery_failed";

export type HeroLayout = "success_card" | "failed_payment" | "pending";

export type ReceiptStatuses = {
  payment: string;
  fulfillment: string;
};

export type BadgeState = {
  payment: {
    label: string;
    variant: StatusBadgeVariant;
  };
  fulfillment: {
    label: string;
    variant: StatusBadgeVariant;
  };
};

export type HeroState = {
  layout: HeroLayout;
  title: string;
  subtitle: string;
  paragraphs: string[];
  detail?: string;
  tone: HeroTone;
  showSpinner: boolean;
  showRetryDelivery: boolean;
};

export type TimelineState = {
  phase: TimelinePhase;
};

const NORMALIZED_STATUSES: NormalizedTransactionStatus[] = [
  "created",
  "payment_pending",
  "payment_failed",
  "payment_success",
  "fulfillment_pending",
  "fulfilled",
  "failed",
];

const DELIVERY_FAILED_COPY = [
  "Your payment has been confirmed.",
  "Unfortunately the service provider could not complete delivery.",
  "You will not be charged again if delivery is retried.",
  "If the issue persists, contact support using your transaction reference.",
];

export function isRetryDeliveryFeatureEnabled(
  enabled = process.env.NEXT_PUBLIC_FEATURE_RETRY_DELIVERY === "true",
): boolean {
  return enabled;
}

export function getNormalizedStatus(
  transaction: TransactionLike,
): NormalizedTransactionStatus {
  const status = transaction.status ?? "created";

  if (
    NORMALIZED_STATUSES.includes(status as NormalizedTransactionStatus)
  ) {
    return status as NormalizedTransactionStatus;
  }

  if (status === "cancelled") {
    return "failed";
  }

  return "payment_pending";
}

export function getReceiptStatuses(
  transaction: TransactionLike,
): ReceiptStatuses {
  switch (getNormalizedStatus(transaction)) {
    case "created":
    case "payment_pending":
      return {
        payment: "Pending",
        fulfillment: "Awaiting Payment",
      };
    case "payment_failed":
      return {
        payment: "Failed",
        fulfillment: "Not Started",
      };
    case "payment_success":
    case "fulfillment_pending":
      return {
        payment: "Successful",
        fulfillment: "Processing",
      };
    case "fulfilled":
      return {
        payment: "Successful",
        fulfillment: "Delivered",
      };
    case "failed":
      return {
        payment: "Successful",
        fulfillment: "Delivery Failed",
      };
  }
}

export function getBadgeState(transaction: TransactionLike): BadgeState {
  switch (getNormalizedStatus(transaction)) {
    case "created":
    case "payment_pending":
      return {
        payment: { label: "Payment Pending", variant: "pending" },
        fulfillment: { label: "Awaiting Payment", variant: "pending" },
      };
    case "payment_failed":
      return {
        payment: { label: "Payment Failed", variant: "failed" },
        fulfillment: { label: "Not Started", variant: "pending" },
      };
    case "payment_success":
    case "fulfillment_pending":
      return {
        payment: { label: "Payment Successful", variant: "success" },
        fulfillment: { label: "Processing", variant: "pending" },
      };
    case "fulfilled":
      return {
        payment: { label: "Payment Successful", variant: "success" },
        fulfillment: { label: "Delivered", variant: "success" },
      };
    case "failed":
      return {
        payment: { label: "Payment Successful", variant: "success" },
        fulfillment: { label: "Delivery Failed", variant: "failed" },
      };
  }
}

export function getTimelineState(transaction: TransactionLike): TimelineState {
  switch (getNormalizedStatus(transaction)) {
    case "fulfilled":
      return { phase: "delivered" };
    case "payment_success":
    case "fulfillment_pending":
      return { phase: "processing" };
    case "failed":
      return { phase: "delivery_failed" };
    case "payment_failed":
      return { phase: "payment_failed" };
    case "created":
    case "payment_pending":
    default:
      return { phase: "awaiting" };
  }
}

export function getHeroState(
  transaction: TransactionLike,
  options?: { retryDeliveryEnabled?: boolean },
): HeroState {
  const status = getNormalizedStatus(transaction);
  const retryDeliveryEnabled = isRetryDeliveryFeatureEnabled(
    options?.retryDeliveryEnabled,
  );

  switch (status) {
    case "fulfilled":
      return {
        layout: "success_card",
        title: "Payment Completed Successfully",
        subtitle: "Your order has been delivered.",
        paragraphs: [],
        tone: "success",
        showSpinner: false,
        showRetryDelivery: false,
      };
    case "payment_success":
    case "fulfillment_pending":
      return {
        layout: "success_card",
        title: "Payment Completed Successfully",
        subtitle: "Delivery is being processed.",
        paragraphs: [],
        detail: "This usually takes 30 seconds to 2 minutes.",
        tone: "success",
        showSpinner: false,
        showRetryDelivery: false,
      };
    case "failed":
      return {
        layout: "success_card",
        title: "Payment Successful, Delivery Failed",
        subtitle: DELIVERY_FAILED_COPY[0],
        paragraphs: DELIVERY_FAILED_COPY.slice(1),
        tone: "delivery_failed",
        showSpinner: false,
        showRetryDelivery: retryDeliveryEnabled,
      };
    case "payment_failed":
      return {
        layout: "failed_payment",
        title: "Payment Failed",
        subtitle: "Your payment could not be completed.",
        paragraphs: [],
        tone: "failed",
        showSpinner: false,
        showRetryDelivery: false,
      };
    case "created":
    case "payment_pending":
      return {
        layout: "pending",
        title: "Payment Pending",
        subtitle: "Your payment is still being confirmed.",
        paragraphs: [],
        tone: "pending",
        showSpinner: true,
        showRetryDelivery: false,
      };
  }
}

export function shouldPollTransactionStatus(
  transaction: TransactionLike,
): boolean {
  const status = getNormalizedStatus(transaction);
  return status === "payment_success" || status === "fulfillment_pending";
}

export function isAwaitingDelivery(transaction: TransactionLike): boolean {
  return shouldPollTransactionStatus(transaction);
}

export function isTerminalTransactionStatus(
  transaction: TransactionLike,
): boolean {
  const status = getNormalizedStatus(transaction);
  return (
    status === "fulfilled" ||
    status === "failed" ||
    status === "payment_failed"
  );
}
