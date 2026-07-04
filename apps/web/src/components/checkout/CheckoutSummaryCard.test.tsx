import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { CheckoutSummaryCard } from "@/components/checkout/CheckoutSummaryCard";
import type { CheckoutFields } from "@/lib/checkout/types";

const baseFields: CheckoutFields = {
  network: "mtn",
  customerPhone: "08031234567",
  recipientPhone: "08031234567",
  useMyNumber: true,
  customerEmail: "",
  dataPlan: "mtn-1.5gb-30",
  disco: "",
  meterType: "prepaid",
  meterNumber: "",
  customerName: "",
};

describe("CheckoutSummaryCard", () => {
  it("renders catalog plan name in review summary", () => {
    render(
      <CheckoutSummaryCard
        product="data"
        fields={baseFields}
        productAmount={1500}
        convenienceFee={100}
        gatewayFee={0}
        payableAmount={1600}
        transactionReference={null}
        pricingMode="estimated"
        transactionReady={false}
        isOverGuestLimit={false}
        dataPlanName="1.5GB - 30 Days"
      />,
    );

    expect(screen.getAllByText("MTN 1.5GB - 30 Days").length).toBeGreaterThan(0);
  });
});
