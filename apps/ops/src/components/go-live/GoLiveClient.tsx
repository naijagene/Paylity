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
  opsPaymentCertificationCreate,
  opsPaymentCertificationExport,
  opsPaymentCertificationFinalize,
  opsPaymentCertificationLinkReference,
  opsPaymentCertificationPreflight,
  opsPaymentCertificationRefresh,
  type OpsGoLiveCheck,
  type OpsGoLiveSnapshot,
  type OpsPaymentCertificationRun,
  resolvePaymentCertification,
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
  const [confirmMaintenance, setConfirmMaintenance] = useState(false);
  const [confirmCertification, setConfirmCertification] = useState(false);
  const [confirmFinalizeCertification, setConfirmFinalizeCertification] = useState(false);
  const [linkReference, setLinkReference] = useState("");

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
            confirmMaintenance={confirmMaintenance}
            onConfirmProductionChange={setConfirmProduction}
            onConfirmMaintenanceChange={setConfirmMaintenance}
            onRefresh={() => void snapshot.refresh()}
            onPreflight={(strict) => void runAction(strict ? "Strict preflight" : "Preflight", () => opsGoLivePreflight(strict))}
            onPricingAudit={() => void runAction("Pricing audit", () => opsGoLivePricingAudit())}
            onBackup={() => void runAction("Backup", () => opsGoLiveBackup())}
            onVerifyBackup={() => void runAction("Verify backup", () => opsGoLiveVerifyBackup())}
            onExportJson={() => void runAction("Export JSON", exportJsonReport)}
            onExportPdf={() => void runAction("Export PDF", async () => exportPdfReport())}
            onSetMode={(mode) =>
              void runAction(`${mode} mode`, () =>
                opsGoLiveSetMode(
                  mode,
                  mode === "live" ? confirmProduction : false,
                  mode === "maintenance" ? confirmMaintenance : false,
                ),
              )
            }
          />
        </header>

        {snapshot.error ? <p className="text-sm text-danger">{snapshot.error}</p> : null}
        {actionError ? <AlertCard severity="critical" message={actionError} /> : null}
        {actionMessage ? <AlertCard severity="success" message={actionMessage} /> : null}
        {snapshot.loading && !data ? <p className="text-sm text-muted">Loading go-live snapshot…</p> : null}

        {data ? (
          <GoLiveSections
            data={data}
            confirmCertification={confirmCertification}
            confirmFinalizeCertification={confirmFinalizeCertification}
            confirmMaintenance={confirmMaintenance}
            linkReference={linkReference}
            onConfirmCertificationChange={setConfirmCertification}
            onConfirmFinalizeChange={setConfirmFinalizeCertification}
            onConfirmMaintenanceChange={setConfirmMaintenance}
            onLinkReferenceChange={setLinkReference}
            onToggleChecklistItem={toggleChecklistItem}
            onAction={(label, action) => void runAction(label, action)}
            onSetMode={(mode) =>
              void runAction(`${mode} mode`, () =>
                opsGoLiveSetMode(
                  mode,
                  mode === "live" ? confirmProduction : false,
                  mode === "maintenance" ? confirmMaintenance : false,
                ),
              )
            }
          />
        ) : null}
      </div>
    </PageContainer>
  );
}

function GoLiveActions({
  confirmProduction,
  confirmMaintenance,
  onConfirmProductionChange,
  onConfirmMaintenanceChange,
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
  confirmMaintenance: boolean;
  onConfirmProductionChange: (value: boolean) => void;
  onConfirmMaintenanceChange: (value: boolean) => void;
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
  const [showMaintenanceDialog, setShowMaintenanceDialog] = useState(false);

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
        <Button type="button" variant="secondary" onClick={() => setShowMaintenanceDialog(true)}>
          Maintenance Mode
        </Button>
        <Button type="button" variant="secondary" onClick={() => onSetMode("soft_launch")}>
          Soft Launch Mode
        </Button>
        <Button type="button" variant="primary" onClick={() => setShowProductionDialog(true)}>
          Production Mode
        </Button>
      </div>
      {showMaintenanceDialog ? (
        <AlertCard
          severity="warning"
          title="Confirm maintenance mode"
          message={
            <div className="space-y-3">
              <p>I understand this blocks new checkout initialization while preserving payment recovery.</p>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={confirmMaintenance}
                  onChange={(event) => onConfirmMaintenanceChange(event.target.checked)}
                />
                Confirm maintenance mode
              </label>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="primary"
                  disabled={!confirmMaintenance}
                  onClick={() => {
                    onSetMode("maintenance");
                    setShowMaintenanceDialog(false);
                  }}
                >
                  Enter Maintenance Mode
                </Button>
                <Button type="button" variant="outline" onClick={() => setShowMaintenanceDialog(false)}>
                  Cancel
                </Button>
              </div>
            </div>
          }
        />
      ) : null}
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
  confirmCertification,
  confirmFinalizeCertification,
  confirmMaintenance,
  linkReference,
  onConfirmCertificationChange,
  onConfirmFinalizeChange,
  onConfirmMaintenanceChange,
  onLinkReferenceChange,
  onToggleChecklistItem,
  onAction,
  onSetMode,
}: {
  data: OpsGoLiveSnapshot;
  confirmCertification: boolean;
  confirmFinalizeCertification: boolean;
  confirmMaintenance: boolean;
  linkReference: string;
  onConfirmCertificationChange: (value: boolean) => void;
  onConfirmFinalizeChange: (value: boolean) => void;
  onConfirmMaintenanceChange: (value: boolean) => void;
  onLinkReferenceChange: (value: string) => void;
  onToggleChecklistItem: (key: string, completed: boolean) => Promise<void>;
  onAction: (label: string, action: () => Promise<unknown>) => void;
  onSetMode: (mode: "staging" | "soft_launch" | "live" | "maintenance") => void;
}) {
  const scheduler = data.launch_status.scheduler;
  const { certification, unavailable } = resolvePaymentCertification(data);

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

      <LivePaymentCertificationSection
        data={data}
        certification={certification}
        unavailable={unavailable}
        confirmCertification={confirmCertification}
        confirmFinalizeCertification={confirmFinalizeCertification}
        confirmMaintenance={confirmMaintenance}
        linkReference={linkReference}
        onConfirmCertificationChange={onConfirmCertificationChange}
        onConfirmFinalizeChange={onConfirmFinalizeChange}
        onConfirmMaintenanceChange={onConfirmMaintenanceChange}
        onLinkReferenceChange={onLinkReferenceChange}
        onAction={onAction}
        onSetMode={onSetMode}
      />

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

function LivePaymentCertificationSection({
  data,
  certification,
  unavailable,
  confirmCertification,
  confirmFinalizeCertification,
  confirmMaintenance,
  linkReference,
  onConfirmCertificationChange,
  onConfirmFinalizeChange,
  onConfirmMaintenanceChange,
  onLinkReferenceChange,
  onAction,
  onSetMode,
}: {
  data: OpsGoLiveSnapshot;
  certification: ReturnType<typeof resolvePaymentCertification>["certification"];
  unavailable: boolean;
  confirmCertification: boolean;
  confirmFinalizeCertification: boolean;
  confirmMaintenance: boolean;
  linkReference: string;
  onConfirmCertificationChange: (value: boolean) => void;
  onConfirmFinalizeChange: (value: boolean) => void;
  onConfirmMaintenanceChange: (value: boolean) => void;
  onLinkReferenceChange: (value: string) => void;
  onAction: (label: string, action: () => Promise<unknown>) => void;
  onSetMode: (mode: "staging" | "soft_launch" | "live" | "maintenance") => void;
}) {
  const activeRun = certification.active_run;
  const lastCertified = certification.last_certified_transaction ?? certification.last_certified ?? null;
  const displayRun = activeRun ?? lastCertified;
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [showFinalizeDialog, setShowFinalizeDialog] = useState(false);
  const [showMaintenanceDialog, setShowMaintenanceDialog] = useState(false);

  return (
    <SectionCard title="Live Payment Certification">
      {unavailable ? (
        <AlertCard
          severity="warning"
          message="Certification status is currently unavailable."
        />
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard label="Paystack Mode" value={certification.paystack_mode} />
        <KpiCard label="Provider Mode" value={certification.provider_mode ?? certification.vtpass_mode} />
        <KpiCard label="Live Preflight Verdict" value={certification.preflight_verdict} />
        <KpiCard label="Launch Mode" value={certification.launch_mode} />
      </div>

      <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div className="rounded-xl border border-border px-4 py-3 text-sm">
          <p className="font-semibold text-dark">Environment & Capacity</p>
          <p className="mt-2 text-muted">Environment: {certification.environment}</p>
          <p className="text-muted">
            Daily transactions: {certification.daily_transaction_usage.transaction_count} /{" "}
            {certification.daily_transaction_usage.transaction_limit_daily || "∞"}
          </p>
          <p className="text-muted">
            Daily revenue: ₦{certification.daily_revenue_usage.gross_collection_naira.toLocaleString()} / ₦
            {(certification.daily_revenue_usage.revenue_limit_daily || 0).toLocaleString() || "∞"}
          </p>
          <p className="mt-2 text-muted">
            Last backup: {formatTimestamp(certification.last_backup_at ?? data.launch_status.backup.last_run_at)}
          </p>
          <p className="text-muted">
            Scheduler: {certification.scheduler_health ?? data.launch_status.scheduler.status}
          </p>
        </div>

        <CertificationRunSummary
          run={activeRun}
          title="Active Certification Run"
          emptyMessage="No active certification session."
        />

        <CertificationRunSummary
          run={lastCertified}
          title="Last Certified Transaction"
          emptyMessage="No certified transaction recorded yet."
          verdict={certification.last_certification_verdict}
        />
      </div>

      {displayRun ? (
        <div className="mt-4 rounded-xl border border-border px-4 py-3 text-sm">
          <p className="font-semibold text-dark">Certification Status</p>
          <ul className="mt-2 grid gap-1 text-muted sm:grid-cols-2">
            <li>Certification verdict: {displayRun.result}</li>
            <li>Payment: {displayRun.payment_status ?? "—"}</li>
            <li>Fulfillment: {displayRun.fulfillment_status ?? "—"}</li>
            <li>Ledger: {displayRun.ledger_status ?? "—"}</li>
            <li>Reconciliation: {displayRun.reconciliation_status ?? "—"}</li>
            <li>Settlement: {displayRun.settlement_expectation_status ?? "—"}</li>
            <li>Receipt: {displayRun.receipt_status ?? "—"}</li>
          </ul>
        </div>
      ) : null}

      <div className="mt-4 flex flex-wrap gap-2">
        <Button type="button" variant="outline" onClick={() => onAction("Live payment preflight", () => opsPaymentCertificationPreflight(false))}>
          Run Live Payment Preflight
        </Button>
        <Button type="button" variant="outline" onClick={() => setShowCreateDialog(true)}>
          Create Certification Session
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={!activeRun || linkReference.trim() === ""}
          onClick={() =>
            activeRun &&
            onAction("Link transaction reference", () =>
              opsPaymentCertificationLinkReference(activeRun.id, linkReference.trim()),
            )
          }
        >
          Link Transaction Reference
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={!activeRun}
          onClick={() => activeRun && onAction("Refresh certification", () => opsPaymentCertificationRefresh(activeRun.id))}
        >
          Refresh Certification
        </Button>
        <Button type="button" variant="outline" disabled={!activeRun} onClick={() => setShowFinalizeDialog(true)}>
          Finalize Certification
        </Button>
        <Button
          type="button"
          variant="outline"
          disabled={!activeRun && !lastCertified}
          onClick={() => {
            const run = activeRun ?? lastCertified;
            if (!run) return;
            return onAction("Export certification evidence", async () => {
              const exported = await opsPaymentCertificationExport(run.id);
              const blob = new Blob([JSON.stringify(exported.payload, null, 2)], {
                type: exported.content_type,
              });
              const url = URL.createObjectURL(blob);
              const anchor = document.createElement("a");
              anchor.href = url;
              anchor.download = exported.filename;
              anchor.click();
              URL.revokeObjectURL(url);
            });
          }}
        >
          Export Certification Evidence
        </Button>
        <Button type="button" variant="secondary" onClick={() => setShowMaintenanceDialog(true)}>
          Enter Maintenance Mode
        </Button>
        <Button type="button" variant="secondary" onClick={() => onSetMode("soft_launch")}>
          Restore Soft Launch Mode
        </Button>
      </div>

      <div className="mt-4">
        <label className="block text-sm font-semibold text-dark" htmlFor="certification-reference">
          Transaction reference to link
        </label>
        <input
          id="certification-reference"
          className="mt-2 w-full rounded-xl border border-border px-3 py-2 text-sm"
          value={linkReference}
          onChange={(event) => onLinkReferenceChange(event.target.value)}
          placeholder="PYL-YYYYMMDD-XXXXXX"
        />
      </div>

      {showMaintenanceDialog ? (
        <AlertCard
          severity="warning"
          title="Confirm maintenance mode"
          message={
            <div className="space-y-3">
              <p>I understand this blocks new checkout initialization while preserving payment recovery.</p>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={confirmMaintenance}
                  onChange={(event) => onConfirmMaintenanceChange(event.target.checked)}
                />
                Confirm maintenance mode
              </label>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="primary"
                  disabled={!confirmMaintenance}
                  onClick={() => {
                    onSetMode("maintenance");
                    setShowMaintenanceDialog(false);
                  }}
                >
                  Enter Maintenance Mode
                </Button>
                <Button type="button" variant="outline" onClick={() => setShowMaintenanceDialog(false)}>
                  Cancel
                </Button>
              </div>
            </div>
          }
        />
      ) : null}

      {showCreateDialog ? (
        <AlertCard
          severity="warning"
          title="Confirm live certification session"
          message={
            <div className="space-y-3">
              <p>
                This creates a controlled ₦100 airtime certification session. Complete checkout through the normal
                customer web flow, then link the resulting transaction reference.
              </p>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={confirmCertification}
                  onChange={(event) => onConfirmCertificationChange(event.target.checked)}
                />
                Confirm live certification session
              </label>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="primary"
                  disabled={!confirmCertification}
                  onClick={() => {
                    onAction("Create certification session", () =>
                      opsPaymentCertificationCreate({
                        product: "airtime",
                        amount: 100,
                        confirm_live_certification: true,
                        force: Boolean(activeRun),
                      }),
                    );
                    setShowCreateDialog(false);
                  }}
                >
                  Create Session
                </Button>
                <Button type="button" variant="outline" onClick={() => setShowCreateDialog(false)}>
                  Cancel
                </Button>
              </div>
            </div>
          }
        />
      ) : null}

      {showFinalizeDialog && activeRun ? (
        <AlertCard
          severity="warning"
          title="Confirm live certification finalization"
          message={
            <div className="space-y-3">
              <p>Finalize only after the real-money transaction evidence has been verified end-to-end.</p>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={confirmFinalizeCertification}
                  onChange={(event) => onConfirmFinalizeChange(event.target.checked)}
                />
                Confirm live certification finalization
              </label>
              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="primary"
                  disabled={!confirmFinalizeCertification}
                  onClick={() => {
                    onAction("Finalize certification", () =>
                      opsPaymentCertificationFinalize(activeRun.id, true),
                    );
                    setShowFinalizeDialog(false);
                  }}
                >
                  Finalize Certification
                </Button>
                <Button type="button" variant="outline" onClick={() => setShowFinalizeDialog(false)}>
                  Cancel
                </Button>
              </div>
            </div>
          }
        />
      ) : null}
    </SectionCard>
  );
}

function CertificationRunSummary({
  run,
  title,
  emptyMessage,
  verdict,
}: {
  run?: OpsPaymentCertificationRun | null;
  title: string;
  emptyMessage: string;
  verdict?: string | null;
}) {
  if (!run) {
    return (
      <div className="rounded-xl border border-border px-4 py-3 text-sm">
        <p className="font-semibold text-dark">{title}</p>
        <p className="mt-2 text-muted">{emptyMessage}</p>
        {verdict ? <p className="mt-1 text-muted">Verdict: {verdict}</p> : null}
      </div>
    );
  }

  return (
    <div className="rounded-xl border border-border px-4 py-3 text-sm">
      <p className="font-semibold text-dark">{title}</p>
      <ul className="mt-2 space-y-1 text-muted">
        <li>Run #{run.id}</li>
        <li>Reference: {run.reference ?? "—"}</li>
        <li>Verdict: {run.result}</li>
        <li>Payment: {run.payment_status ?? "—"}</li>
        <li>Fulfillment: {run.fulfillment_status ?? "—"}</li>
        <li>Ledger: {run.ledger_status ?? "—"}</li>
        <li>Reconciliation: {run.reconciliation_status ?? "—"}</li>
        <li>Settlement: {run.settlement_expectation_status ?? "—"}</li>
        <li>Receipt: {run.receipt_status ?? "—"}</li>
      </ul>
    </div>
  );
}
