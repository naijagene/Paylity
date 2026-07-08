"use client";

import { useEffect, useState } from "react";
import { PageContainer } from "@/components/PageContainer";
import { fetchFeatureFlags } from "@/lib/api/admin";
import { fetchOpsDashboard } from "@/lib/api/ops";
import { fetchPublicHealth } from "@/lib/api/health";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import {
  healthClasses,
  healthLabel,
  mapApiHealth,
  mapDatabaseHealth,
  mapFeatureHealth,
  type HealthIndicator,
} from "@/lib/utils/health";

function mapProviderIndicator(status: string): HealthIndicator {
  if (status === "ok" || status === "skipped") {
    return "healthy";
  }

  if (status === "degraded" || status === "warning") {
    return "warning";
  }

  return "offline";
}

type MonitorItem = {
  label: string;
  indicator: HealthIndicator;
  detail: string;
};

export function MonitoringClient() {
  const [items, setItems] = useState<MonitorItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    Promise.all([fetchPublicHealth(), fetchFeatureFlags(), fetchOpsDashboard()])
      .then(([health, flags, dashboard]) => {
        if (cancelled) {
          return;
        }

        const paystack = flags.find((flag) => flag.key === "paystack");
        const vtpass = dashboard.vtpass;
        const otp = dashboard.fraud;

        setItems([
          {
            label: "API",
            indicator: mapApiHealth(health.status),
            detail: `Environment: ${health.environment ?? "unknown"}`,
          },
          {
            label: "Database",
            indicator: mapDatabaseHealth(health.checks?.database),
            detail: `Status: ${health.checks?.database ?? "unknown"}`,
          },
          {
            label: "Paystack",
            indicator: mapFeatureHealth(Boolean(paystack?.enabled)),
            detail: paystack?.enabled ? "Feature flag enabled" : "Feature flag disabled",
          },
          {
            label: "VTPass",
            indicator: mapProviderIndicator(vtpass?.status ?? health.checks?.vtpass ?? "unknown"),
            detail: vtpass
              ? `${vtpass.environment} · ${vtpass.enabled ? "enabled" : "disabled"} · balance ${vtpass.balance.available ? `₦${vtpass.balance.balance}` : "unavailable"}`
              : `Health: ${health.checks?.vtpass ?? "unknown"}`,
          },
          {
            label: "Queue",
            indicator: mapProviderIndicator(dashboard.providers.queue?.status ?? "unknown"),
            detail: `pending ${dashboard.providers.queue?.pending_jobs ?? 0} · failed ${dashboard.providers.queue?.failed_jobs ?? 0}`,
          },
          {
            label: "OTP",
            indicator: otp?.otp_enabled ? "healthy" : "warning",
            detail: otp
              ? `${otp.otp_pending} pending · ${otp.otp_failures_today} failed today`
              : "OTP monitoring unavailable",
          },
        ]);
      })
      .catch((err) => {
        if (!cancelled) {
          setError(
            err instanceof ApiOfflineError
              ? "Network unavailable."
              : err instanceof ApiError
                ? err.message
                : "Unable to load monitoring data.",
          );
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

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-5xl space-y-6">
        <header>
          <h1 className="font-display text-3xl font-extrabold text-dark">Monitoring</h1>
          <p className="mt-2 text-sm text-muted">
            Track core platform dependencies during soft launch.
          </p>
        </header>

        {error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {error}
          </p>
        ) : null}

        <div className="grid gap-4 sm:grid-cols-2">
          {(loading
            ? ["API", "Database", "Paystack", "VTPass", "Queue", "OTP"]
            : items.map((item) => item.label)
          ).map((label, index) => {
            const item = items[index];
            const indicator = item?.indicator ?? "warning";

            return (
              <div
                key={label}
                className={`rounded-2xl border p-5 ${healthClasses(indicator)}`}
              >
                <p className="text-sm font-semibold">{label}</p>
                <p className="mt-3 text-2xl font-extrabold">
                  {loading ? "…" : healthLabel(indicator)}
                </p>
                <p className="mt-2 text-xs opacity-80">
                  {loading ? "Checking service status" : item?.detail}
                </p>
              </div>
            );
          })}
        </div>
      </div>
    </PageContainer>
  );
}
