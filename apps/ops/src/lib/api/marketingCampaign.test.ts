import { describe, expect, it } from "vitest";
import { formatExpiresAtForBackend, buildOpsMarketingCampaignPayload } from "@/lib/api/ops";
import { formatLaravelValidationErrors, resolveApiErrorMessage } from "@/lib/api/client";

describe("formatLaravelValidationErrors", () => {
  it("combines field messages into a readable string", () => {
    expect(
      formatLaravelValidationErrors({
        expires_at: ["The expires at field must be a valid date."],
        distribution_mode: ["The distribution mode field is required."],
      }),
    ).toBe(
      "The expires at field must be a valid date.\nThe distribution mode field is required.",
    );
  });

  it("ignores business error codes", () => {
    expect(
      formatLaravelValidationErrors({
        code: "VOUCHER_EXPIRED",
        amount: ["The amount field is required."],
      }),
    ).toBe("The amount field is required.");
  });
});

describe("resolveApiErrorMessage", () => {
  it("prefers validation messages over generic text", () => {
    expect(
      resolveApiErrorMessage("The given data was invalid.", {
        name: ["The name field is required."],
      }),
    ).toBe("The name field is required.");
  });
});

describe("buildOpsMarketingCampaignPayload", () => {
  it("matches backend campaign schema for unique codes", () => {
    expect(
      buildOpsMarketingCampaignPayload({
        name: "Soft Launch",
        amount: 500,
        distributionMode: "unique_codes",
        quantity: 5,
        network: "MTN",
        expiresAt: "2026-07-20T10:33",
        active: true,
        onePerPhone: true,
        onePerEmail: true,
        onePerDevice: true,
        reservationTimeoutMinutes: 30,
      }),
    ).toEqual({
      name: "Soft Launch",
      amount: 500,
      distribution_mode: "unique_codes",
      quantity: 5,
      network: "MTN",
      expires_at: formatExpiresAtForBackend("2026-07-20T10:33"),
      active: true,
      one_per_phone: true,
      one_per_email: true,
      one_per_device: true,
      reservation_timeout_minutes: 30,
    });
  });

  it("does not include quantity for shared campaigns", () => {
    const payload = buildOpsMarketingCampaignPayload({
      name: "Airtime Launch Promo",
      amount: 1000,
      distributionMode: "shared_code",
      maxRedemptions: 2,
    });

    expect(payload).not.toHaveProperty("quantity");
    expect(payload.max_redemptions).toBe(2);
  });

  it("includes quantity only for unique campaigns", () => {
    const payload = buildOpsMarketingCampaignPayload({
      name: "Unique Launch",
      amount: 500,
      distributionMode: "unique_codes",
      quantity: 5,
    });

    expect(payload.quantity).toBe(5);
    expect(payload).not.toHaveProperty("max_redemptions");
  });
});

describe("formatExpiresAtForBackend", () => {
  it("returns ISO8601 for datetime-local values", () => {
    const formatted = formatExpiresAtForBackend("2026-07-20T10:33");

    expect(formatted).toMatch(/^2026-07-20T\d{2}:\d{2}:\d{2}\.\d{3}Z$/);
  });

  it("returns null for empty values", () => {
    expect(formatExpiresAtForBackend("")).toBeNull();
    expect(formatExpiresAtForBackend(undefined)).toBeNull();
  });
});
