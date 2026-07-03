import { describe, expect, it } from "vitest";
import {
  getCallbackPageHeading,
  getTimelinePhase,
  shouldRenderCallbackPendingView,
  shouldRenderCallbackSuccessView,
} from "./display";

describe("callback page display helpers", () => {
  it("maps fulfilled to completed success without spinner", () => {
    const heading = getCallbackPageHeading("fulfilled");

    expect(heading.title).toBe("Payment Completed Successfully");
    expect(heading.subtitle).toContain("delivered");
    expect(heading.showSpinner).toBe(false);
    expect(getTimelinePhase("fulfilled")).toBe("delivered");
    expect(shouldRenderCallbackSuccessView("fulfilled")).toBe(true);
    expect(shouldRenderCallbackPendingView("fulfilled")).toBe(false);
  });

  it("maps payment_success to completed success with delivery processing copy", () => {
    const heading = getCallbackPageHeading("payment_success");

    expect(heading.title).toBe("Payment Completed Successfully");
    expect(heading.subtitle).toContain("Delivery is being processed");
    expect(heading.showSpinner).toBe(false);
    expect(getTimelinePhase("payment_success")).toBe("processing");
    expect(shouldRenderCallbackSuccessView("payment_success")).toBe(true);
  });

  it("maps fulfillment_pending to completed success with delivery processing copy", () => {
    const heading = getCallbackPageHeading("fulfillment_pending");

    expect(heading.title).toBe("Payment Completed Successfully");
    expect(heading.subtitle).toContain("Delivery is being processed");
    expect(heading.showSpinner).toBe(false);
    expect(shouldRenderCallbackSuccessView("fulfillment_pending")).toBe(true);
  });

  it("maps failed delivery after successful payment", () => {
    const heading = getCallbackPageHeading("failed");

    expect(heading.title).toBe("Payment Successful, Delivery Failed");
    expect(heading.tone).toBe("delivery_failed");
    expect(heading.showSpinner).toBe(false);
    expect(getTimelinePhase("failed")).toBe("delivery_failed");
    expect(shouldRenderCallbackSuccessView("failed")).toBe(true);
  });

  it("maps payment_failed to failed copy without spinner", () => {
    const heading = getCallbackPageHeading("payment_failed");

    expect(heading.title).toBe("Payment Failed");
    expect(heading.showSpinner).toBe(false);
    expect(shouldRenderCallbackSuccessView("payment_failed")).toBe(false);
  });

  it("maps payment_pending to pending copy with spinner", () => {
    const heading = getCallbackPageHeading("payment_pending");

    expect(heading.title).toBe("Payment Pending");
    expect(heading.showSpinner).toBe(true);
    expect(shouldRenderCallbackPendingView("payment_pending")).toBe(true);
    expect(shouldRenderCallbackSuccessView("payment_pending")).toBe(false);
  });
});
