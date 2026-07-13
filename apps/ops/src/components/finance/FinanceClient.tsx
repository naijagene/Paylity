"use client";

import Link from "next/link";
import { useCallback } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import {
  fetchOpsFinance,
  opsFinanceBackfill,
  opsFinanceClose,
  opsFinanceReconcileSettlements,
  type OpsFinanceSnapshot,
} from "@/lib/api/ops";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { usePolling } from "@/lib/hooks/usePolling";

const POLL_INTERVAL_MS = 30000;

function formatKobo(kobo: number): string {
  return formatNaira(Math.round(kobo / 100));
}

function FinanceAlerts({ alerts }: { alerts: OpsFinanceSnapshot["alerts"] }) {
  if (!alerts.length) {
    return <p className="text-sm text-muted">No financial alerts.</p>;
  }

  return (
    <ul className="space-y-2 text-sm">
      {alerts.map((alert) => (
        <li key={alert.code} className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
          <strong>{alert.code}</strong> — {alert.message}
        </li>
      ))}
    </ul>
  );
}

export function FinanceClient() {
  const loadSnapshot = useCallback(async () => fetchOpsFinance(), []);
  const snapshot = usePolling({ fetcher: loadSnapshot, intervalMs: POLL_INTERVAL_MS });
  const data = snapshot.data;
  const cards = data?.cards;

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-3xl font-extrabold text-dark">Finance Center</h1>
            <p className="mt-2 text-sm text-muted">
              Ledger accountability, settlement visibility, and daily financial close.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={() => void snapshot.refresh()}>
              Refresh
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => void opsFinanceReconcileSettlements(true)}
            >
              Dry Reconcile
            </Button>
            <Button type="button" variant="outline" onClick={() => void opsFinanceBackfill(true)}>
              Dry Backfill
            </Button>
            <Button type="button" variant="secondary" onClick={() => void opsFinanceClose(true)}>
              Dry Close
            </Button>
          </div>
        </header>

        {snapshot.error ? <p className="text-sm text-danger">{snapshot.error}</p> : null}
        {snapshot.loading && !data ? (
          <p className="text-sm text-muted">Loading finance dashboard…</p>
        ) : null}

        {cards ? (
          <>
            <div>
              <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-muted">
                Financial KPIs
              </h2>
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Gross Collection Today" value={formatKobo(cards.gross_collection_today_kobo)} />
                <KpiCard label="Product Value Today" value={formatKobo(cards.product_value_today_kobo)} />
                <KpiCard label="Revenue Today" value={formatKobo(cards.paylity_revenue_today_kobo)} />
                <KpiCard label="Gateway Fees Today" value={formatKobo(cards.gateway_fees_today_kobo)} />
                <KpiCard label="Provider Cost Today" value={formatKobo(cards.provider_cost_today_kobo)} />
                <KpiCard label="Gross Margin Today" value={formatKobo(cards.gross_margin_today_kobo)} />
              </div>
            </div>

            <SectionCard title="Ledger Summary">
              <div className="grid gap-4 sm:grid-cols-2">
                <KpiCard label="Paystack Clearing" value={formatKobo(cards.paystack_clearing_kobo)} />
                <KpiCard label="Settlement Difference" value={formatKobo(cards.settlement_difference_kobo)} />
              </div>
            </SectionCard>
          </>
        ) : null}

        {data ? (
          <>
            <SectionCard title="Financial Alerts">
              <FinanceAlerts alerts={data.alerts} />
            </SectionCard>

            <SectionCard title="Settlement Summary">
              {data.settlement_exceptions.length ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-left text-sm">
                    <thead>
                      <tr className="border-b border-border text-muted">
                        <th className="px-2 py-2">Batch</th>
                        <th className="px-2 py-2">Expected</th>
                        <th className="px-2 py-2">Actual</th>
                        <th className="px-2 py-2">Difference</th>
                        <th className="px-2 py-2">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {data.settlement_exceptions.map((item) => (
                        <tr key={item.reference} className="border-b border-border/60">
                          <td className="px-2 py-2 font-mono text-xs">{item.reference}</td>
                          <td className="px-2 py-2">{formatKobo(item.expected_kobo)}</td>
                          <td className="px-2 py-2">{formatKobo(item.actual_kobo)}</td>
                          <td className="px-2 py-2">{formatKobo(item.difference_kobo)}</td>
                          <td className="px-2 py-2">{item.status}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <p className="text-sm text-muted">No settlement exceptions.</p>
              )}
            </SectionCard>

            <div className="grid gap-6 xl:grid-cols-2">
              <SectionCard title="Daily Close">
                {data.daily_summaries.length ? (
                  <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                      <thead>
                        <tr className="border-b border-border text-muted">
                          <th className="px-2 py-2">Date</th>
                          <th className="px-2 py-2">Collections</th>
                          <th className="px-2 py-2">Revenue</th>
                          <th className="px-2 py-2">Provider Cost</th>
                          <th className="px-2 py-2">Gateway Fees</th>
                          <th className="px-2 py-2">Margin</th>
                          <th className="px-2 py-2">Close</th>
                        </tr>
                      </thead>
                      <tbody>
                        {data.daily_summaries.map((row) => (
                          <tr key={row.date} className="border-b border-border/60">
                            <td className="px-2 py-2">{row.date}</td>
                            <td className="px-2 py-2">{formatKobo(row.collections_kobo)}</td>
                            <td className="px-2 py-2">{formatKobo(row.revenue_kobo)}</td>
                            <td className="px-2 py-2">{formatKobo(row.provider_cost_kobo)}</td>
                            <td className="px-2 py-2">{formatKobo(row.gateway_fee_kobo)}</td>
                            <td className="px-2 py-2">{formatKobo(row.margin_kobo)}</td>
                            <td className="px-2 py-2">{row.close_status}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <p className="text-sm text-muted">No daily close snapshots yet.</p>
                )}
              </SectionCard>

              <SectionCard title="Recent Ledger Postings">
                {data.recent_postings.length ? (
                  <div className="overflow-x-auto">
                    <table className="min-w-full text-left text-sm">
                      <thead>
                        <tr className="border-b border-border text-muted">
                          <th className="px-2 py-2">Posted</th>
                          <th className="px-2 py-2">Reference</th>
                          <th className="px-2 py-2">Event</th>
                          <th className="px-2 py-2">Amount</th>
                        </tr>
                      </thead>
                      <tbody>
                        {data.recent_postings.map((posting) => (
                          <tr key={posting.id} className="border-b border-border/60">
                            <td className="px-2 py-2 text-xs text-muted">
                              {posting.posted_at ? new Date(posting.posted_at).toLocaleString() : "—"}
                            </td>
                            <td className="px-2 py-2 font-semibold">
                              {posting.reference ? (
                                <Link
                                  href={`/transactions/${posting.reference}`}
                                  className="text-success hover:underline"
                                >
                                  {posting.reference}
                                </Link>
                              ) : (
                                "—"
                              )}
                            </td>
                            <td className="px-2 py-2">{posting.event_type}</td>
                            <td className="px-2 py-2">{formatKobo(posting.amount_kobo)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ) : (
                  <p className="text-sm text-muted">No ledger postings yet.</p>
                )}
              </SectionCard>
            </div>
          </>
        ) : null}
      </div>
    </PageContainer>
  );
}
