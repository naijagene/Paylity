"use client";

import Link from "next/link";
import { useCallback } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import {
  fetchOpsReconciliation,
  opsReconcileFulfillment,
  opsReconcilePayment,
  opsResumeAutomation,
  opsRetryReconciliation,
  type OpsReconciliationSnapshot,
} from "@/lib/api/ops";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { usePolling } from "@/lib/hooks/usePolling";

const POLL_INTERVAL_MS = 15000;

function QueueTable({
  title,
  items,
}: {
  title: string;
  items: OpsReconciliationSnapshot["queues"]["payment_exceptions"];
}) {
  return (
    <SectionCard title={title}>
      {items.length === 0 ? (
        <p className="text-sm text-muted">No items in this queue.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="min-w-full text-left text-sm">
            <thead>
              <tr className="border-b border-border text-muted">
                <th className="px-2 py-2">Reference</th>
                <th className="px-2 py-2">Product</th>
                <th className="px-2 py-2">Amount</th>
                <th className="px-2 py-2">Status</th>
                <th className="px-2 py-2">VTPass ID</th>
                <th className="px-2 py-2">Age (min)</th>
                <th className="px-2 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              {items.map((item) => (
                <tr key={item.reference} className="border-b border-border/60">
                  <td className="px-2 py-2 font-semibold">
                    <Link href={`/transactions/${item.reference}`} className="text-success hover:underline">
                      {item.reference}
                    </Link>
                  </td>
                  <td className="px-2 py-2 capitalize">{item.product_type}</td>
                  <td className="px-2 py-2">{formatNaira(item.amount)}</td>
                  <td className="px-2 py-2">{item.payment_state}</td>
                  <td className="px-2 py-2 font-mono text-xs">{item.vtpass_request_id ?? "—"}</td>
                  <td className="px-2 py-2">{item.age_minutes ?? "—"}</td>
                  <td className="px-2 py-2">
                    <div className="flex flex-wrap gap-2">
                      <Button
                        type="button"
                        variant="outline"
                        className="!px-2 !py-1 text-xs"
                        onClick={() => void opsReconcilePayment(item.reference)}
                      >
                        Reconcile Payment
                      </Button>
                      <Button
                        type="button"
                        variant="outline"
                        className="!px-2 !py-1 text-xs"
                        onClick={() => void opsReconcileFulfillment(item.reference)}
                      >
                        Reconcile Fulfillment
                      </Button>
                      <Button
                        type="button"
                        variant="secondary"
                        className="!px-2 !py-1 text-xs"
                        onClick={() => void opsRetryReconciliation(item.reference)}
                      >
                        Retry
                      </Button>
                      {item.needs_manual_review ? (
                        <Button
                          type="button"
                          variant="outline"
                          className="!px-2 !py-1 text-xs"
                          onClick={() => void opsResumeAutomation(item.reference)}
                        >
                          Resume
                        </Button>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </SectionCard>
  );
}

export function ReconciliationClient() {
  const loadSnapshot = useCallback(async () => fetchOpsReconciliation(), []);
  const snapshot = usePolling({ fetcher: loadSnapshot, intervalMs: POLL_INTERVAL_MS });
  const data = snapshot.data;

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header>
          <h1 className="font-display text-3xl font-extrabold text-dark">Reconciliation Center</h1>
          <p className="mt-2 text-sm text-muted">
            Payment and fulfillment exception queues.
          </p>
        </header>

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <KpiCard label="Paid Unfulfilled" value={data?.summary.paid_unfulfilled ?? "…"} />
          <KpiCard label="Stale Payment Pending" value={data?.summary.stale_payment_pending ?? "…"} />
          <KpiCard label="Uncertain Provider" value={data?.summary.uncertain_provider_outcomes ?? "…"} />
          <KpiCard label="Manual Review" value={data?.summary.manual_review ?? "…"} />
          <KpiCard label="Retry Due" value={data?.summary.retry_due ?? "…"} />
          <KpiCard label="Retry Exhausted" value={data?.summary.retry_exhausted ?? "…"} />
          <KpiCard label="Amount Mismatch" value={data?.summary.amount_mismatch ?? "…"} />
          <KpiCard label="Repaired Today" value={data?.summary.repaired_today ?? "…"} />
        </div>

        {data ? (
          <>
            <QueueTable title="Payment Exceptions" items={data.queues.payment_exceptions} />
            <QueueTable title="Fulfillment Exceptions" items={data.queues.fulfillment_exceptions} />
            <QueueTable title="Provider Uncertainty" items={data.queues.provider_uncertainty} />
            <QueueTable title="Manual Review" items={data.queues.manual_review} />
            <QueueTable title="Dead Letters" items={data.queues.dead_letters} />
          </>
        ) : (
          <p className="text-sm text-muted">Loading reconciliation data…</p>
        )}
      </div>
    </PageContainer>
  );
}
