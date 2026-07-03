import { render, screen, waitFor } from "@testing-library/react";
import { useSearchParams } from "next/navigation";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { PaymentCallbackClient } from "./PaymentCallbackClient";
import { verifyPaystackPayment } from "@/lib/api/payments";

vi.mock("next/navigation", () => ({
  useSearchParams: vi.fn(),
}));

vi.mock("@/lib/api/payments", () => ({
  verifyPaystackPayment: vi.fn(),
}));

vi.mock("next/image", () => ({
  default: (props: { alt: string }) => <img alt={props.alt} />,
}));

const mockedUseSearchParams = vi.mocked(useSearchParams);
const mockedVerifyPaystackPayment = vi.mocked(verifyPaystackPayment);

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

  it("shows completed success for fulfilled transactions without pending spinner", async () => {
    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "fulfilled",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(
        screen.getByRole("heading", {
          name: "Payment Completed Successfully",
        }),
      ).toBeInTheDocument();
    });

    expect(screen.getByText("Your order has been delivered.")).toBeInTheDocument();
    expect(screen.queryByText("Payment Pending")).not.toBeInTheDocument();
    expect(document.querySelector(".animate-spin")).not.toBeInTheDocument();
    expect(
      screen.getByLabelText("Order progress timeline"),
    ).toHaveTextContent("Delivered");
    expect(
      screen.getByRole("link", { name: /View Transaction Status/i }),
    ).toBeInTheDocument();
  });

  it("shows delivery processing copy for payment_success without pending spinner", async () => {
    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "payment_success",
      fulfillment_status: "pending",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(
        screen.getByRole("heading", {
          name: "Payment Completed Successfully",
        }),
      ).toBeInTheDocument();
    });

    expect(screen.getByText("Delivery is being processed.")).toBeInTheDocument();
    expect(screen.queryByText("Payment Pending")).not.toBeInTheDocument();
    expect(document.querySelector(".animate-spin")).not.toBeInTheDocument();
    expect(screen.getByText("Processing Order")).toBeInTheDocument();
  });

  it("shows delivery failed copy for failed fulfillment after successful payment", async () => {
    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "failed",
      fulfillment_status: "failed",
      failure_reason: "VTPass timeout",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(
        screen.getByRole("heading", {
          name: "Payment Successful, Delivery Failed",
        }),
      ).toBeInTheDocument();
    });

    expect(
      screen.getByText(
        "Unfortunately the service provider could not complete delivery.",
      ),
    ).toBeInTheDocument();
    expect(screen.getAllByText("VTPass timeout").length).toBeGreaterThan(0);
    expect(document.querySelector(".animate-spin")).not.toBeInTheDocument();
    expect(
      screen.getByLabelText("Status: Delivery Failed"),
    ).toBeInTheDocument();
    expect(
      screen.getAllByLabelText("Status: Payment Successful").length,
    ).toBeGreaterThan(0);
  });

  it("shows retry delivery CTA when feature flag is enabled", async () => {
    vi.stubEnv("NEXT_PUBLIC_FEATURE_RETRY_DELIVERY", "true");

    mockedVerifyPaystackPayment.mockResolvedValue({
      ...baseVerificationResult,
      status: "failed",
      fulfillment_status: "failed",
    });

    render(<PaymentCallbackClient />);

    await waitFor(() => {
      expect(
        screen.getByRole("link", { name: /Retry Delivery/i }),
      ).toBeInTheDocument();
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
        name: "Payment Completed Successfully",
      }),
    ).not.toBeInTheDocument();
  });
});
