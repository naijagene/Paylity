import { describe, expect, it } from "vitest";
import {
  buildCheckoutProductDisplayName,
  formatReceiptTimestamp,
  getReceiptPhoneDisplay,
  getReceiptProductLabel,
} from "@/lib/receipt/display";
import type { TransactionReceipt } from "@/lib/api/transactions";

describe("receipt display helpers", () => {
  it("builds checkout product names", () => {
    expect(
      buildCheckoutProductDisplayName("airtime", {
        network: "mtn",
        customerPhone: "08031234567",
        recipientPhone: "08031234567",
        useMyNumber: true,
        customerEmail: "",
        dataPlan: "",
        disco: "",
        meterType: "prepaid",
        meterNumber: "",
        customerName: "",
      }),
    ).toBe("MTN Airtime");

    expect(
      buildCheckoutProductDisplayName(
        "data",
        {
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
        },
        "1.5GB - 30 Days",
      ),
    ).toBe("MTN 1.5GB - 30 Days");

    expect(
      buildCheckoutProductDisplayName("electricity", {
        network: "",
        customerPhone: "08031234567",
        recipientPhone: "",
        useMyNumber: true,
        customerEmail: "",
        dataPlan: "",
        disco: "ikedc",
        meterType: "prepaid",
        meterNumber: "12345678901",
        customerName: "John Doe",
      }),
    ).toBe("IKEDC Prepaid Electricity");
  });

  it("reads receipt labels and masked phone", () => {
    const receipt: TransactionReceipt = {
      brand: "PAYLITY NG",
      reference: "PYL-20260705-AIR001",
      product_type: "airtime",
      product_label: "MTN Airtime",
      product_display_name: "MTN Airtime",
      customer_phone: "08012345678",
      customer_phone_masked: "0801 XXX 5678",
      phone_display: "0801 XXX 5678",
      product_amount: 1000,
      convenience_fee: 100,
      gateway_fee: 0,
      payable_amount: 1100,
      currency: "NGN",
      status: "fulfilled",
      payment_status: "Payment Successful",
      fulfillment_status: "Delivered",
    };

    expect(getReceiptProductLabel(receipt)).toBe("MTN Airtime");
    expect(getReceiptPhoneDisplay(receipt)).toBe("0801 XXX 5678");
  });

  it("formats receipt timestamp with display override", () => {
    expect(
      formatReceiptTimestamp("2026-07-05T11:07:00.000000Z", "05 Jul 2026, 12:07 AM WAT"),
    ).toBe("05 Jul 2026, 12:07 AM WAT");
  });
});
