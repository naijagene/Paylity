import { describe, expect, it, vi } from "vitest";
import { initializeCheckout, type InitializeCheckoutResponse } from "@/lib/api/checkout";
import {
  getPaystackAuthorizationUrl,
  redirectToPaystackAuthorizationUrl,
  resolveCheckoutPaymentAction,
} from "@/lib/checkout/paystackRedirect";

vi.mock("@/lib/api/checkout", () => ({
  initializeCheckout: vi.fn(),
}));

const mockedInitializeCheckout = vi.mocked(initializeCheckout);

const paystackTransaction: InitializeCheckoutResponse = {
  reference: "PYL-20260709-ABC123",
  product_type: "airtime",
  product_amount: 1000,
  convenience_fee: 100,
  gateway_fee: 0,
  payable_amount: 1100,
  currency: "NGN",
  status: "payment_pending",
  payment_provider: "paystack",
  authorization_url: "https://checkout.paystack.com/test-auth",
  access_code: "ACCESS123",
};

describe("checkout initialize payment redirect", () => {
  it("redirects to paystack when checkout initialize returns authorization_url", async () => {
    mockedInitializeCheckout.mockResolvedValue(paystackTransaction);

    const replace = vi.fn();
    vi.stubGlobal("location", { replace });

    const transaction = await initializeCheckout({
      product_type: "airtime",
      customer_phone: "08031234567",
      product_amount: 1000,
      payload: {
        network: "MTN",
        recipient_phone: "08031234567",
      },
    });

    expect(resolveCheckoutPaymentAction(transaction)).toBe("redirect");

    const authorizationUrl = getPaystackAuthorizationUrl(transaction);
    expect(authorizationUrl).toBe("https://checkout.paystack.com/test-auth");

    if (authorizationUrl) {
      redirectToPaystackAuthorizationUrl(authorizationUrl);
    }

    expect(replace).toHaveBeenCalledWith("https://checkout.paystack.com/test-auth");
  });

  it("flags missing authorization_url for paystack checkout as fallback", async () => {
    mockedInitializeCheckout.mockResolvedValue({
      ...paystackTransaction,
      authorization_url: undefined,
    });

    const transaction = await initializeCheckout({
      product_type: "airtime",
      customer_phone: "08031234567",
      product_amount: 1000,
      payload: {
        network: "MTN",
        recipient_phone: "08031234567",
      },
    });

    expect(resolveCheckoutPaymentAction(transaction)).toBe("fallback");
    expect(getPaystackAuthorizationUrl(transaction)).toBeNull();
  });
});
