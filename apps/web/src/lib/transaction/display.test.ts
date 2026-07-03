import { describe, expect, it } from "vitest";
import {
  getCallbackPageHeading,
  getFulfillmentBadgeLabel,
  getPaymentBadgeLabel,
  getTimelinePhase,
  shouldRenderCallbackPendingView,
  shouldRenderCallbackSuccessView,
} from "./display";

describe("display wrappers over shared status mapper", () => {
  it("maps fulfilled to completed success without spinner", () => {
    const heading = getCallbackPageHeading("fulfilled");

    expect(heading.title).toBe("Payment Completed Successfully");
    expect(heading.subtitle).toContain("delivered");
    expect(heading.showSpinner).toBe(false);
    expect(getTimelinePhase("fulfilled")).toBe("delivered");
    expect(getPaymentBadgeLabel("fulfilled")).toBe("Payment Successful");
    expect(getFulfillmentBadgeLabel("fulfilled")).toBe("Delivered");
    expect(shouldRenderCallbackSuccessView("fulfilled")).toBe(true);
    expect(shouldRenderCallbackPendingView("fulfilled")).toBe(false);
  });

  it("maps payment_success to processing fulfillment consistently", () => {
    const heading = getCallbackPageHeading("payment_success");

    expect(heading.title).toBe("Payment Completed Successfully");
    expect(heading.subtitle).toContain("Delivery is being processed");
    expect(getTimelinePhase("payment_success")).toBe("processing");
    expect(getPaymentBadgeLabel("payment_success")).toBe("Payment Successful");
    expect(getFulfillmentBadgeLabel("payment_success")).toBe("Processing");
  });

  it("maps failed delivery after successful payment", () => {
    const heading = getCallbackPageHeading("failed");

    expect(heading.title).toBe("Payment Successful, Delivery Failed");
    expect(heading.tone).toBe("delivery_failed");
    expect(getPaymentBadgeLabel("failed")).toBe("Payment Successful");
    expect(getFulfillmentBadgeLabel("failed")).toBe("Delivery Failed");
    expect(getTimelinePhase("failed")).toBe("delivery_failed");
  });

  it("maps payment_failed to failed payment and not started fulfillment", () => {
    const heading = getCallbackPageHeading("payment_failed");

    expect(heading.title).toBe("Payment Failed");
    expect(getPaymentBadgeLabel("payment_failed")).toBe("Payment Failed");
    expect(getFulfillmentBadgeLabel("payment_failed")).toBe("Not Started");
    expect(shouldRenderCallbackSuccessView("payment_failed")).toBe(false);
  });

  it("maps payment_pending to pending copy with spinner", () => {
    const heading = getCallbackPageHeading("payment_pending");

    expect(heading.title).toBe("Payment Pending");
    expect(heading.showSpinner).toBe(true);
    expect(getFulfillmentBadgeLabel("payment_pending")).toBe("Awaiting Payment");
  });
});
