import { describe, expect, it, vi } from "vitest";
import type { InitializeCheckoutResponse } from "@/lib/api/checkout";
import {
  MISSING_PAYSTACK_REDIRECT_MESSAGE,
  getPaystackAuthorizationUrl,
  expectsPaystackRedirect,
  redirectToPaystackAuthorizationUrl,
  resolveCheckoutPaymentAction,
} from "./paystackRedirect";

const baseTransaction: InitializeCheckoutResponse = {
  reference: "PYL-20260709-ABC123",
  product_type: "airtime",
  product_amount: 1000,
  convenience_fee: 100,
  gateway_fee: 0,
  payable_amount: 1100,
  currency: "NGN",
  status: "payment_pending",
  payment_provider: "paystack",
};

describe("paystackRedirect", () => {
  it("detects paystack authorization url from checkout response", () => {
    expect(
      getPaystackAuthorizationUrl({
        ...baseTransaction,
        authorization_url: "https://checkout.paystack.com/test-auth",
      }),
    ).toBe("https://checkout.paystack.com/test-auth");
  });

  it("treats blank authorization url as missing", () => {
    expect(
      getPaystackAuthorizationUrl({
        ...baseTransaction,
        authorization_url: "   ",
      }),
    ).toBeNull();
  });

  it("requires redirect when authorization url is present", () => {
    expect(
      resolveCheckoutPaymentAction({
        ...baseTransaction,
        authorization_url: "https://checkout.paystack.com/test-auth",
      }),
    ).toBe("redirect");
  });

  it("requires fallback when paystack checkout has no authorization url", () => {
    expect(resolveCheckoutPaymentAction(baseTransaction)).toBe("fallback");
    expect(expectsPaystackRedirect(baseTransaction)).toBe(true);
  });

  it("completes without paystack redirect in disabled mode", () => {
    expect(
      resolveCheckoutPaymentAction({
        ...baseTransaction,
        status: "created",
        payment_provider: null,
        payment_status: "payment integration coming next",
      }),
    ).toBe("complete");
  });

  it("redirects browser to paystack authorization url", () => {
    const replace = vi.fn();

    vi.stubGlobal("location", {
      replace,
    });

    redirectToPaystackAuthorizationUrl("https://checkout.paystack.com/test-auth");

    expect(replace).toHaveBeenCalledWith("https://checkout.paystack.com/test-auth");
  });

  it("exposes a clear missing redirect message", () => {
    expect(MISSING_PAYSTACK_REDIRECT_MESSAGE).toMatch(/could not be started/i);
  });
});
