import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api/client";
import { fetchOpsDashboard } from "@/lib/api/ops";
import * as operatorAuth from "@/lib/ops/operatorAuth";
import * as operatorKey from "@/lib/ops/operatorKey";

describe("opsRequest auth handling", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    sessionStorage.clear();
  });

  it("locks the console when dashboard returns 401", async () => {
    vi.spyOn(operatorKey, "getOperatorKey").mockReturnValue("wrong-key");
    const handleFailure = vi.spyOn(operatorAuth, "handleOperatorAuthFailure");

    global.fetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({
        success: false,
        message: "Invalid or missing operator access key.",
        errors: { code: "OPERATOR_ACCESS_DENIED" },
      }),
    }) as unknown as typeof fetch;

    await expect(fetchOpsDashboard()).rejects.toBeInstanceOf(ApiError);
    expect(handleFailure).toHaveBeenCalledTimes(1);
  });

  it("locks the console when reports return 403", async () => {
    const { fetchFailedTransactionsReport } = await import("@/lib/api/ops");

    vi.spyOn(operatorKey, "getOperatorKey").mockReturnValue("wrong-key");
    const handleFailure = vi.spyOn(operatorAuth, "handleOperatorAuthFailure");

    global.fetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 403,
      json: async () => ({
        success: false,
        message: "Forbidden",
        errors: { code: "OPERATOR_ACCESS_DENIED" },
      }),
    }) as unknown as typeof fetch;

    await expect(fetchFailedTransactionsReport()).rejects.toBeInstanceOf(ApiError);
    expect(handleFailure).toHaveBeenCalledTimes(1);
  });
});
