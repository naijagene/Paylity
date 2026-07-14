"use client";

import Link from "next/link";
import { useCallback } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import {
  fetchOpsGoLive,
  opsGoLiveBackup,
  opsGoLivePreflight,
  opsGoLivePricingAudit,
  opsGoLiveVerifyBackup,
  type OpsGoLiveSnapshot,
} from "@/lib/api/ops";
import { usePolling } from "@/lib/hooks/usePolling";

const POLL_INTERVAL_MS = 60000;

function statusClass(status: string): string {
  if (status === "READY" || status === "healthy" || status === "PASS") {
    return "text-success";
  }

  if (status === "READY_WITH_WARNINGS" || status === "warning" || status === "WARN") {
    return "text-amber-700";
  }

  return "text-danger";
}

export function GoLiveClient() {
  const loadSnapshot = useCallback(async () => fetchOpsGoLive(), []);
  const snapshot = usePolling({ fetcher: loadSnapshot, intervalMs: POLL_INTERVAL_MS });
  const data = snapshot.data;

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-3xl font-extrabold text-dark">Go-Live Center</h1>
            <p className="mt-2 text-sm text-muted">
              Production switch readiness, provider modes, and launch safeguards.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={() => void snapshot.refresh()}>
              Refresh
            </Button>
            <Button type="button" variant="outline" onClick={() => void opsGoLivePreflight(false)}>
              Run Preflight
            </Button>
            <Button type="button" variant="outline" onClick={() => void opsGoLivePreflight(true)}>
              Strict Preflight
            </Button>
            <Button type="button" variant="outline" onClick={() => void opsGoLivePricingAudit()}>
              Pricing Audit
            </Button>
            <Button type="button" variant="outline" onClick={() => void opsGoLiveBackup()}>
              Create Backup
            </Button>
            <Button type="button" variant="secondary" onClick={() => void opsGoLiveVerifyBackup()}>
              Verify Backup
            </Button>
          </div>
        </header>

        {snapshot.error ? <p className="text-sm text-danger">{snapshot.error}</p> : null}
        {snapshot.loading && !data ? <p className="text-sm text-muted">Loading go-live snapshot…</p> : null}

        {data ? <GoLiveSections data={data} /> : null}
      </div>
    </PageContainer>
  );
}

function GoLiveSections({ data }: { data: OpsGoLiveSnapshot }) {
  return (
    <>
      <SectionCard title="Launch Status">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <KpiCard label="Preflight Status" value={data.launch_status.status} />
          <KpiCard label="Environment" value={data.launch_status.environment} />
          <KpiCard label="Build" value={data.launch_status.build} />
          <KpiCard label="Launch Mode" value={data.launch_mode.mode} />
          <KpiCard label="Scheduler" value={data.launch_status.scheduler.status} />
          <KpiCard
            label="Negative Margins"
            value={String(data.pricing_audit_summary.negative_margin_count)}
          />
        </div>
      </SectionCard>

      <div className="grid gap-6 xl:grid-cols-2">
        <SectionCard title="Provider Mode">
          <ul className="space-y-2 text-sm">
            <li>
              Paystack: <strong>{data.provider_mode.paystack.mode}</strong> (
              {data.provider_mode.paystack.configuration_complete ? "complete" : "incomplete"})
            </li>
            <li>
              VTPass: <strong>{data.provider_mode.vtpass.mode}</strong> (
              {data.provider_mode.vtpass.configuration_complete ? "complete" : "incomplete"})
            </li>
            <li>Callback: {data.provider_mode.paystack.callback_url || "—"}</li>
            <li>Webhook route: {data.provider_mode.paystack.webhook_route}</li>
          </ul>
        </SectionCard>

        <SectionCard title="Security">
          <ul className="space-y-2 text-sm">
            <li>APP_DEBUG: {data.security.app_debug ? "true" : "false"}</li>
            <li>HTTPS APP_URL: {data.security.https_app_url ? "yes" : "no"}</li>
            <li>CORS origins: {data.security.cors_origins.join(", ") || "—"}</li>
          </ul>
        </SectionCard>
      </div>

      <SectionCard title="Finance & Operations">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <KpiCard
            label="Clearing (kobo)"
            value={String(data.finance.paystack_clearing_kobo)}
          />
          <KpiCard
            label="Settlement Diff (kobo)"
            value={String(data.finance.settlement_difference_kobo)}
          />
          <KpiCard
            label="Daily Txn Usage"
            value={`${data.launch_mode.daily_usage.transaction_count}/${data.launch_mode.daily_usage.transaction_limit_daily || "∞"}`}
          />
          <KpiCard
            label="Daily Revenue Usage"
            value={`₦${data.launch_mode.daily_usage.gross_collection_naira}/${data.launch_mode.daily_usage.revenue_limit_daily || "∞"}`}
          />
        </div>
      </SectionCard>

      <SectionCard title="Quick Links">
        <div className="flex flex-wrap gap-3 text-sm">
          <Link href="/monitoring" className="font-semibold text-success hover:underline">
            Open Monitoring
          </Link>
          <Link href="/finance" className="font-semibold text-success hover:underline">
            Open Finance
          </Link>
          <Link href="/reconciliation" className="font-semibold text-success hover:underline">
            Open Reconciliation
          </Link>
          <Link href="/platform" className="font-semibold text-success hover:underline">
            Open Platform
          </Link>
        </div>
      </SectionCard>
    </>
  );
}
