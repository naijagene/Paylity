import { useCallback, useEffect, useRef, useState } from "react";

type UsePollingOptions<T> = {
  enabled?: boolean;
  intervalMs?: number;
  pauseWhenHidden?: boolean;
  fetcher: () => Promise<T>;
  onError?: (error: unknown) => void;
};

type UsePollingResult<T> = {
  data: T | null;
  loading: boolean;
  error: string | null;
  lastUpdated: string | null;
  refresh: () => Promise<void>;
  paused: boolean;
};

export function isPollingPaused(
  enabled: boolean,
  pauseWhenHidden: boolean,
  tabHidden: boolean,
): boolean {
  return !enabled || (pauseWhenHidden && tabHidden);
}

export function usePolling<T>({
  enabled = true,
  intervalMs = 5000,
  pauseWhenHidden = true,
  fetcher,
  onError,
}: UsePollingOptions<T>): UsePollingResult<T> {
  const [data, setData] = useState<T | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [lastUpdated, setLastUpdated] = useState<string | null>(null);
  const [tabHidden, setTabHidden] = useState(
    () => pauseWhenHidden && typeof document !== "undefined" && document.hidden,
  );
  const inFlightRef = useRef(false);

  const paused = isPollingPaused(enabled, pauseWhenHidden, tabHidden);

  const refresh = useCallback(async () => {
    if (inFlightRef.current) {
      return;
    }

    inFlightRef.current = true;

    try {
      const result = await fetcher();
      setData(result);
      setError(null);
      setLastUpdated(new Date().toISOString());
    } catch (err) {
      const message = err instanceof Error ? err.message : "Unable to refresh data.";
      setError(message);
      onError?.(err);
    } finally {
      inFlightRef.current = false;
      setLoading(false);
    }
  }, [fetcher, onError]);

  useEffect(() => {
    if (!pauseWhenHidden) {
      return;
    }

    const handleVisibility = () => {
      setTabHidden(document.hidden);
    };

    handleVisibility();
    document.addEventListener("visibilitychange", handleVisibility);

    return () => {
      document.removeEventListener("visibilitychange", handleVisibility);
    };
  }, [pauseWhenHidden]);

  useEffect(() => {
    if (paused) {
      return;
    }

    let cancelled = false;
    let timer: ReturnType<typeof setInterval> | null = null;

    const run = () => {
      if (!cancelled) {
        void refresh();
      }
    };

    run();
    timer = setInterval(run, intervalMs);

    return () => {
      cancelled = true;

      if (timer) {
        clearInterval(timer);
      }
    };
  }, [paused, intervalMs, refresh]);

  return {
    data,
    loading,
    error,
    lastUpdated,
    refresh,
    paused,
  };
}
