import { describe, expect, it } from "vitest";
import {
  CONVENIENCE_FEE,
  calculateGatewayFee,
  calculatePayableAmount,
} from "@/lib/checkout/pricing";

describe("launch pricing", () => {
  it("calculates gateway fee and payable amount for standard launch amounts", () => {
    const amounts = [100, 200, 500, 1000, 2000, 5000, 10000, 20000];

    for (const amount of amounts) {
      const gatewayFee = calculateGatewayFee(amount, CONVENIENCE_FEE);
      const payable = calculatePayableAmount(amount, gatewayFee);

      expect(gatewayFee).toBeGreaterThan(0);
      expect(payable).toBe(amount + CONVENIENCE_FEE + gatewayFee);
    }
  });

  it("matches backend quote for ₦1,000 airtime", () => {
    expect(calculateGatewayFee(1000, CONVENIENCE_FEE)).toBe(118);
    expect(calculatePayableAmount(1000, 118)).toBe(1218);
  });
});
