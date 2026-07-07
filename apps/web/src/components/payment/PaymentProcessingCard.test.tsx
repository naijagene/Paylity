import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { PaymentProcessingCard } from "./PaymentProcessingCard";

vi.mock("next/image", () => ({
  default: (props: { alt: string }) => <img alt={props.alt} />,
}));

describe("PaymentProcessingCard", () => {
  it("renders branded processing copy and timeline", () => {
    render(<PaymentProcessingCard reference="PYL-20260706-PROC01" />);

    expect(
      screen.getByRole("heading", {
        name: "We're processing your transaction",
      }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "This usually takes a few seconds. Please do not close this page.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText("Payment Received")).toBeInTheDocument();
    expect(
      screen.getByText("Your request is being processed"),
    ).toBeInTheDocument();
    expect(screen.getByText("Delivering to Recipient")).toBeInTheDocument();
    expect(screen.getByText("Your transaction is secure")).toBeInTheDocument();
    expect(screen.getByText("Secure & Encrypted")).toBeInTheDocument();
    expect(
      screen.getByText(
        "Pay bills, buy airtime & data, and more — all in one place.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText("Preparing your purchase...")).toBeInTheDocument();
    expect(screen.getByRole("progressbar")).toBeInTheDocument();
  });
});
