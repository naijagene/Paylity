import { describe, expect, it } from "vitest";
import { ApiError } from "@/lib/api/client";
import { isOperatorAuthError } from "@/lib/ops/operatorAuth";

describe("operatorAuth", () => {
  it("detects operator auth errors from 401 responses", () => {
    expect(
      isOperatorAuthError(
        new ApiError("Invalid or missing operator access key.", {
          code: "OPERATOR_ACCESS_DENIED",
        }, 401),
      ),
    ).toBe(true);
  });

  it("detects operator auth errors from 403 responses", () => {
    expect(isOperatorAuthError(new ApiError("Forbidden", {}, 403))).toBe(true);
  });

  it("ignores unrelated api errors", () => {
    expect(
      isOperatorAuthError(new ApiError("Validation failed", { code: "VALIDATION" }, 422)),
    ).toBe(false);
  });
});
