import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { TransactionReceiptCard } from "@/components/transaction/TransactionReceiptCard";

vi.mock("next/image", () => ({
  default: (props: { alt: string }) => <img alt={props.alt} />,
}));

describe("TransactionReceiptCard", () => {
  const baseProps = {
    reference: "PYL-20260705-AIR001",
    productLabel: "MTN Airtime",
    customerPhone: "0801 XXX 5678",
    productAmount: 1000,
    convenienceFee: 100,
    gatewayFee: 0,
    payableAmount: 1100,
    transactionStatus: "fulfilled",
  };

  it("renders masked phone", () => {
    render(<TransactionReceiptCard {...baseProps} />);

    expect(screen.getByText("0801 XXX 5678")).toBeInTheDocument();
  });

  it("renders timestamp", () => {
    render(
      <TransactionReceiptCard
        {...baseProps}
        timestampDisplay="05 Jul 2026, 12:07 AM WAT"
      />,
    );

    expect(screen.getByText("05 Jul 2026, 12:07 AM WAT")).toBeInTheDocument();
  });

  it("renders descriptive product name", () => {
    render(<TransactionReceiptCard {...baseProps} />);

    expect(screen.getByText("MTN Airtime")).toBeInTheDocument();
  });

  it("renders failure reason for failed receipts", () => {
    render(
      <TransactionReceiptCard
        {...baseProps}
        transactionStatus="failed"
        failureReason="VTPass timeout"
      />,
    );

    expect(screen.getByText("VTPass timeout")).toBeInTheDocument();
  });

  it("renders email when provided", () => {
    render(
      <TransactionReceiptCard
        {...baseProps}
        customerEmail="buyer@example.com"
      />,
    );

    expect(screen.getByText("buyer@example.com")).toBeInTheDocument();
  });
});
