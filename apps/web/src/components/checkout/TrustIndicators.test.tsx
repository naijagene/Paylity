import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { TrustIndicators } from "./TrustIndicators";

describe("TrustIndicators", () => {
  it("renders secure payment, instant delivery, and digital receipt", () => {
    render(<TrustIndicators />);

    expect(screen.getByText("Secure Payment")).toBeInTheDocument();
    expect(screen.getByText("Instant Delivery")).toBeInTheDocument();
    expect(screen.getByText("Digital Receipt")).toBeInTheDocument();
  });
});
