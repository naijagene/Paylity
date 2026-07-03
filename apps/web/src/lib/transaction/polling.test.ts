import { describe, expect, it } from "vitest";
import {
  DEFAULT_MAX_POLL_ATTEMPTS,
  hasPollingExhausted,
  shouldContinuePolling,
} from "./polling";

describe("transaction polling", () => {
  it("continues polling while payment is successful and attempts remain", () => {
    expect(
      shouldContinuePolling("payment_success", 1, DEFAULT_MAX_POLL_ATTEMPTS),
    ).toBe(true);
  });

  it("stops polling after max attempts for pending delivery", () => {
    expect(
      shouldContinuePolling(
        "payment_success",
        DEFAULT_MAX_POLL_ATTEMPTS,
        DEFAULT_MAX_POLL_ATTEMPTS,
      ),
    ).toBe(false);
    expect(
      hasPollingExhausted(
        "payment_success",
        DEFAULT_MAX_POLL_ATTEMPTS,
        DEFAULT_MAX_POLL_ATTEMPTS,
      ),
    ).toBe(true);
  });

  it("does not mark polling exhausted for fulfilled transactions", () => {
    expect(
      hasPollingExhausted(
        "fulfilled",
        DEFAULT_MAX_POLL_ATTEMPTS,
        DEFAULT_MAX_POLL_ATTEMPTS,
      ),
    ).toBe(false);
  });
});
