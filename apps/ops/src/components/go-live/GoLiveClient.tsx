"use client";

import Link from "next/link";
import { useCallback, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { AlertCard } from "@/components/ui/AlertCard";
import { HealthCard, KpiCard, SectionCard } from "@/components/ui/OpsCards";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import {
  fetchOpsGoLive,
  getOpsGoLiveExportPdfUrl,
  opsGoLiveBackup,
  opsGoLiveExportJson,
  opsGoLivePreflight,
  opsGoLivePricingAudit,
  opsGoLiveSetMode,
  opsGoLiveUpdateChecklist,
  opsGoLiveVerifyBackup,
  type OpsGoLiveCheck,
  type OpsGoLiveSnapshot,
} from "@/lib/api/ops";
import { getOperatorKey } from "@/lib/ops/operatorKey";
import { usePolling } from "@/lib/hooks/usePolling";
import { formatRelativeTimestamp } from "@/lib/utils/dashboard";

const POLL_INTERVAL_MS = 60000;

function preflightVariant(status: string): "success" | "processing" | "failed" | "info" {
  if (status === "READY") return "success";
  if (status === "READY_WITH_WARNINGS") return "processing";
  if (status === "BLOCKED") return "failed";
  return "info";
}

function schedulerVariant(status: string): "success" | "processing" | "failed" | "info" {
  if (status === "healthy") return "success";
  if (status === "warning") return "processing";
  if (status === "critical") return "failed";
  return "info";
}

function checkVariant(status: string): "success" | "processing" | "failed" | "info" {
  if (status === "PASS") return "success";
  if (status === "WARN") return "processing";
  if (status === "FAIL") return "failed";
  return "info";
}

function formatTimestamp(value?: string | null): string {
  if (!value) return "—";
  return formatRelativeTimestamp(value);
}

export function GoLiveClient() {
  const [actionMessage, setActionMessage] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [confirmProduction, setConfirmProduction] = useState(false);

  const loadSnapshot = useCallback(async () => fetchOpsGoLive(), []);
  const snapshot = usePolling({ fetcher: loadSnapshot, intervalMs: POLL_INTERVAL_MS });
  const data = snapshot.data;

  async function runAction(label: string, action: () => Promise<unknown>) {
    setActionError(null);
    setActionMessage(null);

    try {
      await action();
      setActionMessage(`${label} completed.`);
      await snapshot.refresh();
    } catch (error) {
      setActionError(error instanceof Error ? error.message : `${label} failed.`);
    }
  }

  async function toggleChecklistItem(key: string, completed: boolean) {
    await runAction("Checklist update", async () => {
      await opsGoLiveUpdateChecklist({ [key]: completed });
    });
  }

  async function exportJsonReport() {
    const report = await opsGoLiveExportJson();
    const blob = new Blob([JSON.stringify(report, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = `paylity-launch-report-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-")}.json`;
    anchor.click();
    URL.revokeObjectURL(url);
  }

  async function exportPdfReport() {
    const operatorKey = getOperatorKey();
    const response = await fetch(getOpsGoLiveExportPdfUrl(), {
      headers: {
        Accept: "text/html",
        "X-Operator-Key": operatorKey ?? "",
      },
    });

    if (!response.ok) {
      throw new Error("PDF export failed.");
    }

    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = `paylity-launch-report-${new Date().toISOString().slice(0, 19).replace(/[:T]/g, "-")}.html`;
    anchor.click();
    URL.revokeObjectURL(url);
  }

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="font-display text-3xl font-extrabold text-dark">Go-Live Center</h1>
              {data ? (
                <StatusBadge
                  label={data.launch_status.environment_badge.label}
                  variant={data.launch_status.environment_badge.variant}
                />
              ) : null}
            </div>
            <p className="mt-2 text-sm text-muted">
              Production switch readiness, provider modes, and launch safeguards.
            </p>
          </div>
          <GoLiveActions
            confirmProduction={confirmProduction}
            onConfirmProductionChange={setConfirmProduction}
            onRefresh={() => void snapshot.refresh()}
            onPreflight={(strict) => void runAction(strict ? "Strict preflight" : "Preflight", () => opsGoLivePreflight(strict))}
            onPricingAudit={() => void runAction("Pricing audit", () => opsGoLivePricingAudit())}
            onBackup={() => void runAction("Backup", () => opsGoLiveBackup())}
            onVerifyBackup={() => void runAction("Verify backup", () => opsGoLiveVerifyBackup())}
            onExportJson={() => void runAction("Export JSON", exportJsonReport)}
            onExportPdf={() => void runAction("Export PDF", async () => exportPdfReport())}
            onSetMode={(mode) =>
              void runAction(`${mode} mode`, () =>
                opsGoLiveSetMode(mode, mode === "live" ? confirmProduction : false),
              )
            }
          />
        </header>

        {snapshot.error ? <p className="text-sm text-danger">{snapshot.error}</p> : null}
        {actionError ? <AlertCard severity="critical" message={actionError} /> : null}
        {actionMessage ? <AlertCard severity="success" message={actionMessage} /> : null}
        {snapshot.loading && !data ? <p className="text-sm text-muted">Loading go-live snapshot…</p> : null}

        {data ? <GoLiveSections data={data} onToggleChecklistItem={toggleChecklistItem} /> : null}
      </div>
    </PageContainer>
  );
}

function GoLiveActions({
  confirmProduction,
  onConfirmProductionChange,
  onRefresh,
  onPreflight,
  onPricingAudit,
  onBackup,
  onVerifyBackup,
  onExportJson,
  onExportPdf,
  onSetMode,
}: {
  confirmProduction: boolean;
  onConfirmProductionChange: (value: boolean) => void;
  onRefresh: () => void;
  onPreflight: (strict: boolean) => void;
  onPricingAudit: () => void;
  onBackup: () => void;
  onVerifyBackup: () => void;
  onExportJson: () => void;
  onExportPdf: () => void;
  onSetMode: (mode: "staging" | "soft_launch" | "live" | "maintenance") => void;
}) {
  const [showProductionDialog, setShowProductionDialog] = useState(false);

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="outline" onClick={onRefresh}>
          Refresh
        </Button>
        <Button type="button" variant="outline" onClick={() => onPreflight(false)}>
          Run Preflight
        </Button>
        <Button type="button" variant="outline" onClick={() => onPreflight(true)}>
          Strict Preflight
        </Button>
        <Button type="button" variant="outline" onClick={onPricingAudit}>
          Pricing Audit
        </Button>
        <Button type="button" variant="outline" onClick={onBackup}>
          Backup
        </Button>
        <Button type="button" variant="outline" onClick={onVerifyBackup}>
          Verify Backup
        </Button>
        <Button type="button" variant="outline" onClick={onExportJson}>
          Export Report
        </Button>
        <Button type="button" variant="outline" onClick={onExportPdf}>
          Export PDF
        </Button>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="secondary" onClick={() => onSetMode("maintenance")}>
          Maintenance Mode
        </Button>
        <Button type="button" variant="secondary" onClick={() => onSetMode("soft_launch")}>
          Soft Launch Mode
        </Button>
        <Button type="button" variant="primary" onClick={() => setShowProductionDialog(true)}>
          Production Mode
        </Button>
      </div>
      {showProductionDialog ? (
        <AlertCard
          severity="warning"
          title="Confirm production switch"
          message={
            <div className="space-y-3">
              <p>I understand this switches PAYLITY into live production.</p>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={confirmProduction}
                  onChange={(event) => onConfirmProductionChange(event.target.checked)}
                />
                Confirm production switch
              </label>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="primary"
                  disabled={!confirmProduction}
                  onClick={() => {
                    onSetMode("live");
                    setShowProductionDialog(false);
                  }}
                >
                  Switch to Production
                </Button>
                <Button type="button" variant="outline" onClick={() => setShowProductionDialog(false)}>
                  Cancel
                </Button>
              </div>
            </div>
          }
        />
      ) : null}
    </div>
  );
}

function GoLiveSections({
  data,
  onToggleChecklistItem,
}: {
  data: OpsGoLiveSnapshot;
  onToggleChecklistItem: (key: string, completed: boolean) => Promise<void>;
}) {
  const scheduler = data.launch_status.scheduler;

  return (
    <>
      <SectionCard title="Launch Status">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <KpiCard label="Preflight Status" value={data.preflight.status} />
          <KpiCard label="Launch Mode" value={data.launch_mode.mode} />
          <KpiCard label="Build" value={data.launch_status.build} />
          <KpiCard
            label="Checklist Progress"
            value={`${data.checklist.progress_pct}%`}
            hint={`${data.checklist.completed_count}/${data.checklist.total_count} complete`}
          />
        </div>
      </SectionCard>

      <div className="grid gap-6 xl:grid-cols-2">
        <SectionCard title="Scheduler Health">
          <div className="space-y-4">
            <HealthCard
              label="Scheduler"
              status={scheduler.status}
              detail={`Last heartbeat: ${formatTimestamp(scheduler.last_run ?? scheduler.last_run_at)}`}
            />
            <ul className="space-y-2 text-sm text-muted">
              <li>
                Seconds since last run:{" "}
                <strong className="text-dark">
                  {scheduler.seconds_since_last_run ?? scheduler.age_seconds ?? "—"}
                </strong>
              </li>
              <li>
                Next expected: <strong className="text-dark">{formatTimestamp(scheduler.next_expected_run)}</strong>
              </li>
            </ul>
            <StatusBadge label={scheduler.status} variant={schedulerVariant(scheduler.status)} />
          </div>
        </SectionCard>

        <SectionCard title="Launch Blockers">
          {data.blockers.length === 0 ? (
            <AlertCard severity="success" message="No launch blockers detected." />
          ) : (
            <div className="space-y-3">
              {data.blockers.map((blocker) => (
                <AlertCard
                  key={blocker.code}
                  severity={blocker.severity === "critical" ? "critical" : "warning"}
                  title={blocker.code}
                  message={blocker.message}
                />
              ))}
            </div>
          )}
        </SectionCard>
      </div>

      <SectionCard title="Detailed Preflight">
        <div className="mb-4 flex flex-wrap items-center gap-3">
          <StatusBadge label={data.preflight.status} variant={preflightVariant(data.preflight.status)} />
          <span className="text-sm text-muted">
            Pass {data.preflight.summary.pass} · Warn {data.preflight.summary.warn} · Fail{" "}
            {data.preflight.summary.fail}
          </span>
        </div>
        <div className="grid gap-3 md:grid-cols-2">
          {data.preflight.checks.map((check) => (
            <PreflightCheckRow key={check.name} check={check} />
          ))}
        </div>
      </SectionCard>

      <SectionCard title="Production Checklist">
        <div className="mb-4">
          <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
            <div
              className="h-full rounded-full bg-success transition-all"
              style={{ width: `${data.checklist.progress_pct}%` }}
            />
          </div>
          <p className="mt-2 text-sm text-muted">
            {data.checklist.completed_count} of {data.checklist.total_count} items complete ({data.checklist.progress_pct}%)
          </p>
          {data.checklist.ready_for_production ? (
            <p className="mt-3 font-display text-lg font-extrabold text-success">READY FOR PRODUCTION</p>
          ) : null}
        </div>
        <div className="grid gap-2 md:grid-cols-2">
          {data.checklist.items.map((item) => (
            <label
              key={item.key}
              className="flex items-center gap-3 rounded-xl border border-border px-3 py-2 text-sm"
            >
              <input
                type="checkbox"
                checked={item.completed}
                onChange={(event) => void onToggleChecklistItem(item.key, event.target.checked)}
              />
              <span className={item.completed ? "text-dark" : "text-muted"}>{item.label}</span>
            </label>
          ))}
        </div>
      </SectionCard>

      <SectionCard title="Launch Timeline">
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          <TimelineItem label="Last Backup" value={data.timeline.last_backup} />
          <TimelineItem label="Last Verify Backup" value={data.timeline.last_verify_backup} />
          <TimelineItem label="Last Pricing Audit" value={data.timeline.last_pricing_audit} />
          <TimelineItem label="Last Preflight" value={data.timeline.last_preflight} />
          <TimelineItem label="Last Financial Close" value={data.timeline.last_financial_close} />
          <TimelineItem label="Last Settlement" value={data.timeline.last_settlement} />
          <TimelineItem label="Last Scheduler Heartbeat" value={data.timeline.last_scheduler_heartbeat} />
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

        <SectionCard title="Security & Finance">
          <ul className="space-y-2 text-sm">
            <li>APP_DEBUG: {data.security.app_debug ? "true" : "false"}</li>
            <li>HTTPS APP_URL: {data.security.https_app_url ? "yes" : "no"}</li>
            <li>Negative margins: {data.finance.negative_margin_count}</li>
            <li>Clearing (kobo): {data.finance.paystack_clearing_kobo}</li>
            <li>Settlement diff (kobo): {data.finance.settlement_difference_kobo}</li>
          </ul>
        </SectionCard>
      </div>

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

function PreflightCheckRow({ check }: { check: OpsGoLiveCheck }) {
  return (
    <div className="rounded-xl border border-border px-3 py-3">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-semibold text-dark">{check.name}</p>
          <p className="mt-1 text-xs text-muted">{check.message}</p>
        </div>
        <StatusBadge label={check.status} variant={checkVariant(check.status)} />
      </div>
    </div>
  );
}

function TimelineItem({ label, value }: { label: string; value?: string | null }) {
  return (
    <div className="rounded-xl border border-border px-3 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide text-muted">{label}</p>
      <p className="mt-1 text-sm font-semibold text-dark">{formatTimestamp(value)}</p>
    </div>
  );
}
