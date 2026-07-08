"use client";

import { usePlatformStatus } from "@/hooks/usePlatformStatus";

export function IncidentModeBanner() {
  const { status, loading } = usePlatformStatus();

  if (loading || !status.message) {
    return null;
  }

  const isIncident = status.incident_mode;

  return (
    <div
      role="status"
      className={
        isIncident
          ? "border-b border-amber-300 bg-amber-50 px-4 py-3 text-center text-sm font-medium text-amber-900"
          : "border-b border-slate-200 bg-slate-100 px-4 py-3 text-center text-sm font-medium text-slate-700"
      }
    >
      {status.message}
    </div>
  );
}
