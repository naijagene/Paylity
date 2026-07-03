import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { CHECKOUT_STORAGE_KEY } from "@/lib/checkout/constants";
import {
  clearTransactionSession,
  getTransactionSession,
  isTerminalTransactionStatus,
  isTransactionSessionExpired,
  isTransactionSessionResumable,
  pruneTransactionSession,
  resolveActiveTransactionReference,
  saveTransactionSession,
  shouldResumeStoredTransaction,
  TRANSACTION_SESSION_STORAGE_KEY,
} from "./session";

describe("transaction session", () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date("2026-07-03T12:00:00.000Z"));
    sessionStorage.clear();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllEnvs();
    sessionStorage.clear();
  });

  it("expires session after TTL", () => {
    vi.stubEnv("NEXT_PUBLIC_TRANSACTION_SESSION_TTL_MINUTES", "10");

    saveTransactionSession("PYL-20260703-ABC123", "payment_success", "airtime");

    const session = getTransactionSession();
    expect(session).not.toBeNull();
    expect(isTransactionSessionExpired(session)).toBe(false);

    vi.setSystemTime(new Date("2026-07-03T12:09:59.000Z"));
    expect(isTransactionSessionExpired(session)).toBe(false);

    vi.setSystemTime(new Date("2026-07-03T12:10:01.000Z"));
    expect(isTransactionSessionExpired(session)).toBe(true);
  });

  it("does not resume terminal transactions", () => {
    saveTransactionSession("PYL-20260703-TERM01", "fulfilled", "airtime");

    expect(isTerminalTransactionStatus("fulfilled")).toBe(true);
    expect(isTransactionSessionResumable()).toBe(false);
    expect(shouldResumeStoredTransaction("PYL-20260703-TERM01")).toBe(false);
    expect(resolveActiveTransactionReference()).toBeNull();
  });

  it("resumes active transaction within TTL", () => {
    saveTransactionSession(
      "PYL-20260703-ACTIVE",
      "payment_success",
      "data",
    );

    expect(isTransactionSessionResumable()).toBe(true);
    expect(shouldResumeStoredTransaction("PYL-20260703-ACTIVE")).toBe(true);
    expect(resolveActiveTransactionReference()).toBe("PYL-20260703-ACTIVE");
  });

  it("prefers URL reference over stored session", () => {
    saveTransactionSession("PYL-20260703-STORED", "payment_success", "airtime");

    expect(resolveActiveTransactionReference("PYL-20260703-URLREF")).toBe(
      "PYL-20260703-URLREF",
    );
    expect(shouldResumeStoredTransaction("PYL-20260703-OTHER")).toBe(false);
  });

  it("clears session and checkout transaction fields", () => {
    sessionStorage.setItem(
      CHECKOUT_STORAGE_KEY,
      JSON.stringify({
        product: "airtime",
        step: "review",
        transactionRef: "PYL-20260703-CLEAR1",
        transactionInitialized: true,
      }),
    );
    saveTransactionSession("PYL-20260703-CLEAR1", "fulfilled", "airtime");

    clearTransactionSession();

    expect(sessionStorage.getItem(TRANSACTION_SESSION_STORAGE_KEY)).toBeNull();

    const checkout = JSON.parse(
      sessionStorage.getItem(CHECKOUT_STORAGE_KEY) ?? "{}",
    ) as {
      transactionRef: string | null;
      transactionInitialized: boolean;
      step: string;
    };

    expect(checkout.transactionRef).toBeNull();
    expect(checkout.transactionInitialized).toBe(false);
    expect(checkout.step).toBe("form");
  });

  it("prunes expired and terminal sessions", () => {
    saveTransactionSession("PYL-20260703-OLD", "payment_success", "airtime");

    vi.setSystemTime(new Date("2026-07-03T12:20:00.000Z"));
    pruneTransactionSession();
    expect(getTransactionSession()).toBeNull();

    saveTransactionSession("PYL-20260703-FAIL", "failed", "airtime");
    pruneTransactionSession();
    expect(getTransactionSession()).toBeNull();
  });
});
