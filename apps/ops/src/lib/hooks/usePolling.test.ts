import { afterEach, describe, expect, it, vi } from "vitest";
import { act, renderHook, waitFor } from "@testing-library/react";
import { isPollingPaused, usePolling } from "@/lib/hooks/usePolling";

describe("isPollingPaused", () => {
  it("pauses when disabled or tab hidden", () => {
    expect(isPollingPaused(false, true, false)).toBe(true);
    expect(isPollingPaused(true, true, true)).toBe(true);
    expect(isPollingPaused(true, true, false)).toBe(false);
    expect(isPollingPaused(true, false, true)).toBe(false);
  });
});

describe("usePolling", () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("fetches immediately when polling is active", async () => {
    const fetcher = vi.fn().mockResolvedValue({ value: 1 });

    const { result } = renderHook(() =>
      usePolling({
        fetcher,
        intervalMs: 5000,
        pauseWhenHidden: false,
      }),
    );

    await waitFor(() => {
      expect(result.current.data).toEqual({ value: 1 });
    });

    expect(fetcher).toHaveBeenCalledTimes(1);
  });

  it("pauses polling when the tab is hidden", async () => {
    Object.defineProperty(document, "hidden", {
      configurable: true,
      writable: true,
      value: true,
    });

    const fetcher = vi.fn().mockResolvedValue({ value: 1 });

    const { result } = renderHook(() =>
      usePolling({
        fetcher,
        intervalMs: 50,
        pauseWhenHidden: true,
      }),
    );

    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 150));
    });

    expect(result.current.paused).toBe(true);
    expect(fetcher).not.toHaveBeenCalled();
  });
});
