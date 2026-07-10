import { describe, expect, it } from "vitest";
import {
  isOperatorKeyFormatValid,
  normalizeOperatorKeyInput,
} from "@/lib/ops/operatorSession";

describe("operatorSession", () => {
  it("rejects blank keys", () => {
    expect(isOperatorKeyFormatValid("")).toBe(false);
    expect(isOperatorKeyFormatValid("   ")).toBe(false);
  });

  it("accepts valid operator key format", () => {
    expect(isOperatorKeyFormatValid("test-operator-key")).toBe(true);
  });

  it("normalizes operator key input", () => {
    expect(normalizeOperatorKeyInput("  test-operator-key  ")).toBe(
      "test-operator-key",
    );
  });
});
