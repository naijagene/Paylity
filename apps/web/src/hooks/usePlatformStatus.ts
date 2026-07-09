"use client";

import { useEffect, useState } from "react";
import { fetchPlatformStatus, type PlatformStatus } from "@/lib/api/platform";
import { ApiOfflineError } from "@/lib/api/client";

const DEFAULT_STATUS: PlatformStatus = {
  checkout_enabled: true,
  maintenance_mode: false,
  incident_mode: false,
  message: null,
};

export function usePlatformStatus() {
  const [status, setStatus] = useState<PlatformStatus>(DEFAULT_STATUS);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    fetchPlatformStatus()
      .then((data) => {
        if (!cancelled) {
          setStatus(data);
          setError(null);
        }
      })
      .catch((err) => {
        if (cancelled) {
          return;
        }

        if (err instanceof ApiOfflineError) {
          setError(err.message);
        } else if (err instanceof Error) {
          setError(err.message);
        } else {
          setError("Unable to load platform status.");
        }

        setStatus(DEFAULT_STATUS);
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return { status, loading, error };
}
