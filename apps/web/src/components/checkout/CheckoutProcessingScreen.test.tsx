import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { CheckoutProcessingScreen } from "./CheckoutProcessingScreen";

vi.mock("next/image", () => ({
  default: (props: { alt: string }) => <img alt={props.alt} />,
}));

describe("CheckoutProcessingScreen", () => {
  it("renders branded processing content for checkout initialization", () => {
    render(
      <CheckoutProcessingScreen
        product="airtime"
        transactionRef="PYL-20260706-INIT01"
      />,
    );

    expect(
      screen.getByRole("heading", {
        name: "We're processing your request",
      }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "Please keep this page open. Your purchase is being securely processed.",
      ),
    ).toBeInTheDocument();
    expect(screen.getByText("Preparing payment...")).toBeInTheDocument();
    expect(
      screen.getByText("Usually takes less than 15 seconds"),
    ).toBeInTheDocument();
    expect(screen.getByText(/PYL-20260706-INIT01/)).toBeInTheDocument();
    expect(
      screen.getByLabelText("Advertisement placeholder"),
    ).toBeInTheDocument();
  });
});
