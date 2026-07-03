import { describe, expect, it } from "vitest";
import {
  getBadgeState,
  getHeroState,
  getNormalizedStatus,
  getReceiptStatuses,
  getTimelineState,
  type NormalizedTransactionStatus,
} from "@paylity/shared/payment/statusMapper";

const ALL_STATUSES: NormalizedTransactionStatus[] = [
  "created",
  "payment_pending",
  "payment_failed",
  "payment_success",
  "fulfillment_pending",
  "fulfilled",
  "failed",
];

function assertUnifiedStatus(status: NormalizedTransactionStatus) {
  const transaction = { status };
  const hero = getHeroState(transaction, { retryDeliveryEnabled: false });
  const badges = getBadgeState(transaction);
  const receipt = getReceiptStatuses(transaction);
  const timeline = getTimelineState(transaction);

  return { hero, badges, receipt, timeline };
}

describe("getNormalizedStatus", () => {
  it.each(ALL_STATUSES)("normalizes %s", (status) => {
    expect(getNormalizedStatus({ status })).toBe(status);
  });

  it("maps cancelled to failed", () => {
    expect(getNormalizedStatus({ status: "cancelled" })).toBe("failed");
  });

  it("defaults unknown statuses to payment_pending", () => {
    expect(getNormalizedStatus({ status: "unknown" })).toBe("payment_pending");
  });
});

describe("unified callback status synchronization", () => {
  it("created and payment_pending share receipt and badge mapping", () => {
    for (const status of ["created", "payment_pending"] as const) {
      const { badges, receipt, hero } = assertUnifiedStatus(status);

      expect(receipt).toEqual({
        payment: "Pending",
        fulfillment: "Awaiting Payment",
      });
      expect(badges.payment.label).toBe("Payment Pending");
      expect(badges.fulfillment.label).toBe("Awaiting Payment");
      expect(hero.layout).toBe("pending");
      expect(hero.showSpinner).toBe(true);
    }
  });

  it("payment_failed shows failed payment and not started fulfillment", () => {
    const { badges, receipt, hero, timeline } =
      assertUnifiedStatus("payment_failed");

    expect(receipt).toEqual({
      payment: "Failed",
      fulfillment: "Not Started",
    });
    expect(badges.payment.label).toBe("Payment Failed");
    expect(badges.fulfillment.label).toBe("Not Started");
    expect(hero.title).toBe("Payment Failed");
    expect(hero.layout).toBe("failed_payment");
    expect(timeline.phase).toBe("payment_failed");
  });

  it("payment_success and fulfillment_pending share processing fulfillment", () => {
    for (const status of ["payment_success", "fulfillment_pending"] as const) {
      const { badges, receipt, hero, timeline } = assertUnifiedStatus(status);

      expect(receipt).toEqual({
        payment: "Successful",
        fulfillment: "Processing",
      });
      expect(badges.payment.label).toBe("Payment Successful");
      expect(badges.fulfillment.label).toBe("Processing");
      expect(hero.title).toBe("Payment Completed Successfully");
      expect(hero.layout).toBe("success_card");
      expect(timeline.phase).toBe("processing");
    }
  });

  it("fulfilled shows successful payment and delivered fulfillment everywhere", () => {
    const { badges, receipt, hero, timeline } = assertUnifiedStatus("fulfilled");

    expect(receipt).toEqual({
      payment: "Successful",
      fulfillment: "Delivered",
    });
    expect(badges.payment.label).toBe("Payment Successful");
    expect(badges.fulfillment.label).toBe("Delivered");
    expect(hero.title).toBe("Payment Completed Successfully");
    expect(hero.subtitle).toContain("delivered");
    expect(timeline.phase).toBe("delivered");
  });

  it("failed shows successful payment and delivery failed everywhere", () => {
    const { badges, receipt, hero, timeline } = assertUnifiedStatus("failed");

    expect(receipt).toEqual({
      payment: "Successful",
      fulfillment: "Delivery Failed",
    });
    expect(badges.payment.label).toBe("Payment Successful");
    expect(badges.fulfillment.label).toBe("Delivery Failed");
    expect(hero.title).toBe("Payment Successful, Delivery Failed");
    expect(hero.paragraphs).toEqual([
      "Unfortunately the service provider could not complete delivery.",
      "You will not be charged again if delivery is retried.",
      "If the issue persists, contact support using your transaction reference.",
    ]);
    expect(timeline.phase).toBe("delivery_failed");
  });

  it("enables retry delivery CTA only for failed status when feature flag is on", () => {
    expect(
      getHeroState({ status: "failed" }, { retryDeliveryEnabled: false })
        .showRetryDelivery,
    ).toBe(false);
    expect(
      getHeroState({ status: "failed" }, { retryDeliveryEnabled: true })
        .showRetryDelivery,
    ).toBe(true);
    expect(
      getHeroState({ status: "fulfilled" }, { retryDeliveryEnabled: true })
        .showRetryDelivery,
    ).toBe(false);
  });
});

describe("cross-surface consistency", () => {
  it.each(ALL_STATUSES)(
    "keeps receipt payment label aligned with badge payment label semantics for %s",
    (status) => {
      const { badges, receipt } = assertUnifiedStatus(status);

      if (receipt.payment === "Successful") {
        expect(badges.payment.label).toBe("Payment Successful");
      }

      if (receipt.payment === "Failed") {
        expect(badges.payment.label).toBe("Payment Failed");
      }

      if (receipt.payment === "Pending") {
        expect(badges.payment.label).toBe("Payment Pending");
      }
    },
  );

  it.each(ALL_STATUSES)(
    "keeps receipt fulfillment label aligned with badge fulfillment label for %s",
    (status) => {
      const { badges, receipt } = assertUnifiedStatus(status);

      const receiptToBadge: Record<string, string> = {
        "Awaiting Payment": "Awaiting Payment",
        "Not Started": "Not Started",
        Processing: "Processing",
        Delivered: "Delivered",
        "Delivery Failed": "Delivery Failed",
      };

      expect(badges.fulfillment.label).toBe(receiptToBadge[receipt.fulfillment]);
    },
  );
});
