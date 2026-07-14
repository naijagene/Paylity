import { describe, expect, it } from "vitest";
import { buildInitializeCheckoutPayload } from "@/lib/api/checkout";
import { buildCampaignShareMessage, buildShareLinks } from "@/lib/api/reviews";

const baseFields = {
  customerPhone: "08031234567",
  customerEmail: "",
  network: "MTN",
  recipientPhone: "08031234567",
  dataPlan: "",
  disco: "",
  meterType: "prepaid" as const,
  meterNumber: "",
  customerName: "",
  useMyNumber: true,
  meterVerified: false,
};

describe("voucher checkout payload", () => {
  it("includes voucher_code and device_id when a voucher is applied", () => {
    const payload = buildInitializeCheckoutPayload(
      "airtime",
      baseFields,
      500,
      null,
      null,
      "PYL-7K9M-Q2TX",
      "device-abc-123",
    );

    expect(payload.voucher_code).toBe("PYL-7K9M-Q2TX");
    expect(payload.device_id).toBe("device-abc-123");
    expect(payload).not.toHaveProperty("voucher_discount_amount");
  });

  it("omits voucher fields when no voucher is applied", () => {
    const payload = buildInitializeCheckoutPayload("airtime", baseFields, 500);

    expect(payload.voucher_code).toBeUndefined();
    expect(payload.device_id).toBeUndefined();
  });
});

describe("campaign sharing", () => {
  it("does not include transaction reference or receipt url", () => {
    const message = buildCampaignShareMessage();
    const links = buildShareLinks("https://paylity.ng/airtime");

    expect(message).not.toMatch(/Ref:/i);
    expect(message).toContain("PAYLITY");
    expect(decodeURIComponent(links.whatsapp)).not.toContain("/transaction/");
  });
});
