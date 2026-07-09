import { screen, waitFor } from "@testing-library/react";
import { useParams } from "next/navigation";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { TransactionStatusClient } from "./TransactionStatusClient";
import { getTransaction } from "@/lib/api/transactions";
import { renderWithProviders } from "@/test/renderWithProviders";

vi.mock("next/navigation", () => ({
  useParams: vi.fn(),
  useRouter: vi.fn(() => ({
    push: vi.fn(),
    replace: vi.fn(),
  })),
}));

vi.mock("@/lib/api/transactions", () => ({
  getTransaction: vi.fn(),
}));

vi.mock("next/image", () => ({
  default: (props: { alt: string }) => <img alt={props.alt} />,
}));

const mockedUseParams = vi.mocked(useParams);
const mockedGetTransaction = vi.mocked(getTransaction);

describe("TransactionStatusClient", () => {
  beforeEach(() => {
    mockedUseParams.mockReturnValue({
      reference: "PYL-20260709-ABC123",
    });
  });

  it("remains accessible by transaction reference", async () => {
    mockedGetTransaction.mockResolvedValue({
      reference: "PYL-20260709-ABC123",
      product_type: "airtime",
      customer_phone: "08031234567",
      customer_email: null,
      customer_name: null,
      product_amount: 1000,
      convenience_fee: 100,
      gateway_fee: 0,
      payable_amount: 1100,
      currency: "NGN",
      status: "payment_pending",
      payment_provider: "paystack",
      payment_reference: "PYL-20260709-ABC123",
      payment_authorization_url: "https://checkout.paystack.com/test-auth",
      fulfillment_provider: null,
      fulfillment_reference: null,
      failure_reason: null,
      verified_phone: false,
      created_at: "2026-07-09T12:00:00Z",
      updated_at: "2026-07-09T12:00:00Z",
      timeline: [],
      is_terminal: false,
      poll_interval_seconds: 5,
    });

    renderWithProviders(<TransactionStatusClient />);

    await waitFor(() => {
      expect(mockedGetTransaction).toHaveBeenCalledWith("PYL-20260709-ABC123");
    });

    expect(screen.getAllByText("PYL-20260709-ABC123").length).toBeGreaterThan(0);
  });
});
