import { describe, expect, it } from "vitest";
import {
  extractElectricityTokenDetails,
  getPrimaryElectricityToken,
} from "./electricity";

describe("electricity token extraction", () => {
  it("extracts token fields from nested fulfillment payload", () => {
    const details = extractElectricityTokenDetails({
      content: {
        transactions: {
          token: "1234-5678-9012-3456",
          units: "50.0",
          tariff: "R2",
        },
      },
    });

    expect(details).toEqual({
      token: "1234-5678-9012-3456",
      units: "50.0",
      tariff: "R2",
    });
    expect(getPrimaryElectricityToken(details)).toBe("1234-5678-9012-3456");
  });

  it("returns null when no token fields are present", () => {
    expect(extractElectricityTokenDetails({ code: "000" })).toBeNull();
  });
});
