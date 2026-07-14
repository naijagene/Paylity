import { describe, expect, it } from "vitest";
import {
  CONVENIENCE_FEE,
  calculateGatewayFee,
  calculatePayableAmount,
  calculatePricingWithVoucher,
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

  it("reduces payable amount when a voucher discount is applied", () => {
    const without = calculatePricingWithVoucher(1000, 0);
    const withVoucher = calculatePricingWithVoucher(1000, 500);

    expect(withVoucher.voucherDiscountAmount).toBe(500);
    expect(withVoucher.payableAmount).toBeLessThan(without.payableAmount);
    expect(withVoucher.convenienceFee).toBe(CONVENIENCE_FEE);
  });

  it("matches backend voucher pricing audit scenarios", () => {
    const scenarios = [
      { product: 500, voucher: 500, net: 0, preGateway: 100, gateway: 103, payable: 203 },
      { product: 1000, voucher: 500, net: 500, preGateway: 600, gateway: 111, payable: 711 },
      { product: 1000, voucher: 1000, net: 0, preGateway: 100, gateway: 103, payable: 203 },
      { product: 2000, voucher: 1000, net: 1000, preGateway: 1100, gateway: 118, payable: 1218 },
    ];

    for (const scenario of scenarios) {
      const pricing = calculatePricingWithVoucher(scenario.product, scenario.voucher);

      expect(pricing.netProductAmount).toBe(scenario.net);
      expect(pricing.preGatewayCharge).toBe(scenario.preGateway);
      expect(pricing.gatewayFee).toBe(scenario.gateway);
      expect(pricing.payableAmount).toBe(scenario.payable);
      expect(pricing.payableAmount).toBeGreaterThan(0);
    }
  });

  it("recovers gateway fee from pre-gateway charge when product is fully discounted", () => {
    const pricing = calculatePricingWithVoucher(500, 500);

    expect(pricing.netProductAmount).toBe(0);
    expect(pricing.preGatewayCharge).toBe(CONVENIENCE_FEE);
    expect(pricing.gatewayFee).toBe(calculateGatewayFee(0, CONVENIENCE_FEE));
    expect(pricing.gatewayFee).toBeGreaterThan(calculateGatewayFee(0, 0));
  });
});
