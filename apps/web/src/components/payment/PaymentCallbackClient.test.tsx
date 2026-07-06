import { render, screen, waitFor } from "@testing-library/react";
import { useSearchParams } from "next/navigation";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { PaymentCallbackClient } from "./PaymentCallbackClient";
import { verifyPaystackPayment } from "@/lib/api/payments";
import { getTransaction } from "@/lib/api/transactions";

const mockReplace = vi.fn();

vi.mock("next/navigation", () => ({
  useSearchParams: vi.fn(),
  useRouter: vi.fn(() => ({
    replace: mockReplace,
  })),
}));

vi.mock("@/lib/api/payments", () => ({
  verifyPaystackPayment: vi.fn(),
}));

vi.mock("@/lib/api/transactions", () => ({
  getTransaction: vi.fn(),
}));

vi.mock("next/image", () => ({
  default: (props: { alt: string }) => <img alt={props.alt} />,
}));

const mockedUseSearchParams = vi.mocked(useSearchParams);
const mockedVerifyPaystackPayment = vi.mocked(verifyPaystackPayment);
const mockedGetTransaction = vi.mocked(getTransaction);

const baseVerificationResult = {
  reference: "PYL-20260703-FULFIL",
  payment_status: "success",
  product_type: "airtime",
  product_amount: 1000,
  convenience_fee: 100,
  gateway_fee: 0,
  payable_amount: 1100,
  currency: "NGN",
  verified_at: "2026-07-03T12:00:00Z",
  fulfillment_status: "fulfilled",
};

describe("PaymentCallbackClient", () => {
  beforeEach(() => {
    mockedUseSearchParams.mockReturnValue(
      new URLSearchParams("reference=PYL-20260703-FULFIL") as ReturnType<
        typeof mockedUseSearchParams
      >,
    );
  });

  afterEach(() => {
    vi.clearAllMocks();
    vi.unstubAllEnvs();
  });

  it("shows processing card immediately before verify resolves", () => {
    let resolveVerify!: (value: typeof baseVerificationResult & { status: string }) => void;
    mockedVerifyPaystackPayment.mockReturnValue(
      new Promise((resolve) => {
        resolveVerify = resolve;
      }),
    );

    render(<PaymentCallbackClient />);

    expect(
      screen.getByRole("heading", {
        name: "We're processing your transaction",
      }),
    ).toBeInTheDocument();
    expect(screen.queryByTestId("payment-verification-skeleton")).not.toBeInTheDocument();

    resolveVerify({
      ...baseVerificationResult,
      status: "payment_success",
      fulfillment_status: "pending",
    });
  });

  it("redirects fulfilled transactions to the status page", async () => {
    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "fulfilled",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith(
        "/transaction/PYL-20260703-FULFIL",
      );
    });
  });

  it("shows branded processing page for payment_success", async () => {
    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "payment_success",
      fulfillment_status: "pending",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(
        screen.getByRole("heading", {
          name: "We're processing your transaction",
        }),
      ).toBeInTheDocument();
    });

    expect(
      screen.getByText(
        "This usually takes a few seconds. Please do not close this page.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText("Your transaction is secure")).toBeInTheDocument();
    expect(
      screen.getByText(
        "Pay bills, buy airtime & data, and more — all in one place.",
      ),
    ).toBeInTheDocument();
    expect(screen.queryByText("Payment Pending")).not.toBeInTheDocument();
    expect(
      screen.queryByRole("heading", {
        name: "Payment Completed Successfully",
      }),
    ).not.toBeInTheDocument();
  });

  it("redirects once delivery status resolves during polling", async () => {
    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "payment_success",
      fulfillment_status: "pending",
    });
    mockedGetTransaction.mockResolvedValue({
      reference: "PYL-20260703-FULFIL",
      product_type: "airtime",
      customer_phone: "08031234567",
      product_amount: 1000,
      convenience_fee: 100,
      gateway_fee: 0,
      payable_amount: 1100,
      currency: "NGN",
      status: "fulfilled",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(
        screen.getByRole("heading", {
          name: "We're processing your transaction",
        }),
      ).toBeInTheDocument();
    });

    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith(
        "/transaction/PYL-20260703-FULFIL",
      );
    });
  });

  it("shows delivery failed copy for failed fulfillment after successful payment", async () => {
    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "failed",
      fulfillment_status: "failed",
      failure_reason: "Delivery timeout",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith(
        "/transaction/PYL-20260703-FULFIL",
      );
    });
  });

  it("shows payment_failed badges consistently", async () => {
    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "payment_failed",
      payment_status: "failed",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(
        screen.getByLabelText("Status: Payment Failed"),
      ).toBeInTheDocument();
    });

    expect(screen.getByLabelText("Status: Not Started")).toBeInTheDocument();
  });

  it("shows pending spinner only while payment is pending", async () => {
    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "payment_pending",
      payment_status: "pending",
      fulfillment_status: "pending",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(
        screen.getByRole("heading", { name: "Payment Pending" }),
      ).toBeInTheDocument();
    });

    expect(document.querySelector(".animate-spin")).toBeInTheDocument();
    expect(
      screen.queryByRole("heading", {
        name: "We're processing your transaction",
      }),
    ).not.toBeInTheDocument();
  });
});
