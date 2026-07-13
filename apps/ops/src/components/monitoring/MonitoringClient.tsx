"use client";

import { useCallback } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { fetchOpsMonitoring, refreshVtpassWallet } from "@/lib/api/ops";
import { usePolling } from "@/lib/hooks/usePolling";
import {
  formatRelativeTimestamp,
  formatVtpassBalance,
  formatWalletHealth,
  walletHealthIndicator,
} from "@/lib/utils/dashboard";
import {
  healthClasses,
  healthLabel,
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

const DEFAULT_POLL_MS = 60000;

export function MonitoringClient() {
  const loadMonitoring = useCallback(async () => fetchOpsMonitoring(), []);
  const monitoring = usePolling({
    fetcher: loadMonitoring,
    intervalMs: DEFAULT_POLL_MS,
  });

  const handleWalletRefresh = async () => {
    await refreshVtpassWallet();
    await monitoring.refresh();
  };

  const data = monitoring.data;
  const wallet = data?.wallet ?? data?.vtpass?.balance;
  const error = monitoring.error;

  const items: MonitorItem[] = data
    ? [
        {
          label: "VTPass Wallet",
          indicator: walletHealthIndicator(wallet?.health),
          detail: wallet
            ? `${formatVtpassBalance(wallet)} · ${formatWalletHealth(wallet.health)} · updated ${formatRelativeTimestamp(wallet.checked_at)}`
            : "Wallet data unavailable",
        },
        {
          label: "VTPass Provider",
          indicator: mapProviderIndicator(data.vtpass?.status ?? "unknown"),
          detail: data.vtpass
            ? `${data.vtpass.environment} · ${data.vtpass.enabled ? "enabled" : "disabled"}`
            : "Provider status unavailable",
        },
        {
          label: "Queue",
          indicator: mapProviderIndicator(data.queue?.status ?? "unknown"),
          detail: `pending ${data.queue?.pending_jobs ?? 0} · failed ${data.queue?.failed_jobs ?? 0}`,
        },
        {
          label: "OTP",
          indicator: data.otp?.enabled ? "healthy" : "warning",
          detail: data.otp
            ? `${data.otp.pending} pending · ${data.otp.failed_today} failed today`
            : "OTP monitoring unavailable",
        },
      ]
    : [];

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-5xl space-y-6">
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <h1 className="font-display text-3xl font-extrabold text-dark">Monitoring</h1>
            <p className="mt-2 text-sm text-muted">
              Track core platform dependencies and VTPass wallet readiness during soft launch.
            </p>
          </div>
          <Button type="button" variant="outline" onClick={() => void handleWalletRefresh()}>
            Refresh Wallet
          </Button>
        </header>

        {error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {error}
          </p>
        ) : null}

        <div className="grid gap-4 sm:grid-cols-2">
          {(monitoring.loading && items.length === 0
            ? Array.from({ length: 4 }, (_, index) => ({
                label: `Loading ${index + 1}`,
                indicator: "warning" as const,
                detail: "…",
              }))
            : items
          ).map((item) => (
            <div
              key={item.label}
              className={`rounded-2xl border p-4 ${healthClasses(item.indicator)}`}
            >
              <p className="text-sm font-semibold">{item.label}</p>
              <p className="mt-2 text-lg font-extrabold">{healthLabel(item.indicator)}</p>
              <p className="mt-1 text-xs opacity-80">{item.detail}</p>
            </div>
          ))}
        </div>

        {wallet ? (
          <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <h2 className="font-display text-lg font-extrabold text-dark">VTPass Wallet</h2>
            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-muted">Balance</dt>
                <dd className="font-semibold text-dark">{formatVtpassBalance(wallet)}</dd>
              </div>
              <div>
                <dt className="text-muted">Health</dt>
                <dd className="font-semibold text-dark">{formatWalletHealth(wallet.health)}</dd>
              </div>
              <div>
                <dt className="text-muted">Last Refresh</dt>
                <dd className="font-semibold text-dark">
                  {formatRelativeTimestamp(wallet.checked_at)}
                  {wallet.cached ? " (cached)" : ""}
                </dd>
              </div>
              <div>
                <dt className="text-muted">Provider Status</dt>
                <dd className="font-semibold text-dark">{data?.vtpass?.status ?? "unknown"}</dd>
              </div>
            </dl>
          </section>
        ) : null}
      </div>
    </PageContainer>
  );
}
