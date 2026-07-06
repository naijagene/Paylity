export const PRODUCT_LABELS: Record<string, string> = {
  airtime: "Airtime",
  data: "Data",
  electricity: "Electricity",
};

export type {
  HeroLayout,
  HeroState,
  HeroTone,
  NormalizedTransactionStatus,
  ReceiptStatuses,
  StatusBadgeVariant,
  TimelinePhase,
  TransactionLike,
} from "@paylity/shared/payment/statusMapper";

export {
  getBadgeState,
  getHeroState,
  getNormalizedStatus,
  getReceiptStatuses,
  getTimelineState,
  isRetryDeliveryFeatureEnabled,
} from "@paylity/shared/payment/statusMapper";

import {
  getBadgeState,
  getHeroState,
  getTimelineState,
  isAwaitingDelivery as isAwaitingDeliveryShared,
  isTerminalTransactionStatus as isTerminalTransactionStatusShared,
  shouldPollTransactionStatus as shouldPollTransactionStatusShared,
  getNormalizedStatus,
  type HeroState,
  type StatusBadgeVariant,
  type TimelinePhase,
  type TransactionLike,
} from "@paylity/shared/payment/statusMapper";

export type CallbackPageTone = HeroState["tone"];
export type CallbackPageHeading = HeroState;

export function getPaymentBadgeVariant(status: string): StatusBadgeVariant {
  return getBadgeState({ status }).payment.variant;
}

export function getFulfillmentBadgeVariant(status: string): StatusBadgeVariant {
  return getBadgeState({ status }).fulfillment.variant;
}

export function getPaymentBadgeLabel(status: string): string {
  return getBadgeState({ status }).payment.label;
}

export function getFulfillmentBadgeLabel(status: string): string {
  return getBadgeState({ status }).fulfillment.label;
}

export function getTimelinePhase(status: string): TimelinePhase {
  return getTimelineState({ status }).phase;
}

export function getCallbackPageHeading(status: string): HeroState {
  return getHeroState({ status });
}

export function shouldRenderCallbackSuccessView(status: string): boolean {
  return getHeroState({ status }).layout === "success_card";
}

export function shouldRenderCallbackPendingView(status: string): boolean {
  return getHeroState({ status }).layout === "pending";
}

export function toTransactionLike(status: string): TransactionLike {
  return { status };
}

export function shouldPollTransactionStatus(status: string): boolean {
  return shouldPollTransactionStatusShared({ status });
}

export function isAwaitingDelivery(status: string): boolean {
  return isAwaitingDeliveryShared({ status });
}

export function isTerminalTransactionStatus(status: string): boolean {
  return isTerminalTransactionStatusShared({ status });
}

export function shouldShowFulfillmentProcessingPage(status: string): boolean {
  return shouldPollTransactionStatus(status);
}

export function shouldRedirectToTransactionStatus(status: string): boolean {
  const normalized = getNormalizedStatus({ status });
  return normalized === "fulfilled" || normalized === "failed";
}
