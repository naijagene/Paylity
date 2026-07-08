"use client";

import { useEffect, useState } from "react";
import { fetchPlatformStatus, type PlatformStatus } from "@/lib/api/platform";

const DEFAULT_STATUS: PlatformStatus = {
  checkout_enabled: true,
  maintenance_mode: false,
  incident_mode: false,
  message: null,
};

export function usePlatformStatus() {
  const [status, setStatus] = useState<PlatformStatus>(DEFAULT_STATUS);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;

    fetchPlatformStatus()
      .then((data) => {
        if (!cancelled) {
          setStatus(data);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setStatus(DEFAULT_STATUS);
        }
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

  return { status, loading };
}
