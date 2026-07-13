"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { KpiCard } from "@/components/ui/OpsCards";
import {
  fetchDailyReconciliation,
  fetchFailedTransactionsReport,
  fetchOpsMonitoring,
  fetchOpsSummary,
  fetchRetrySummary,
  fetchSettlementSummary,
  searchOpsTransactions,
} from "@/lib/api/ops";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { exportCsv } from "@/lib/utils/csv";

export function ReportsClient() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [revenue, setRevenue] = useState("—");
  const [transactions, setTransactions] = useState("—");
  const [reconciliation, setReconciliation] = useState<Awaited<
    ReturnType<typeof fetchDailyReconciliation>
  > | null>(null);
  const [settlement, setSettlement] = useState<Awaited<
    ReturnType<typeof fetchSettlementSummary>
  > | null>(null);
  const [retrySummary, setRetrySummary] = useState<Awaited<
    ReturnType<typeof fetchRetrySummary>
  > | null>(null);

  useEffect(() => {
    let cancelled = false;

    Promise.all([
      fetchOpsSummary(),
      fetchOpsMonitoring(),
      fetchDailyReconciliation(),
      fetchSettlementSummary(),
      fetchRetrySummary(),
    ])
      .then(([summary, monitoring, reconciliationData, settlementData, retryData]) => {
        if (!cancelled) {
          setRevenue(formatNaira(monitoring.revenue ?? summary.revenue_today ?? 0));
          setTransactions(String(monitoring.transactions ?? summary.total_transactions_today));
          setReconciliation(reconciliationData);
          setSettlement(settlementData);
          setRetrySummary(retryData);
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(
            err instanceof ApiOfflineError
              ? "Network unavailable."
              : err instanceof ApiError
                ? err.message
                : "Unable to load reports.",
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

  const handleExportTransactions = async () => {
    const result = await searchOpsTransactions({ per_page: 100 });
    exportCsv("paylity-transactions-report.csv", [
      ["Reference", "Product", "Phone", "Amount", "Status", "Created"],
      ...result.items.map((item) => [
        item.reference,
        item.product_type,
        item.customer_phone,
        String(item.payable_amount),
        item.status,
        item.created_at ?? "",
      ]),
    ]);
  };

  const handleExportFailed = async () => {
    const items = await fetchFailedTransactionsReport();
    exportCsv("paylity-failed-transactions.csv", [
      ["Reference", "Product", "Phone", "Amount", "Status", "Failure Reason", "Created"],
      ...items.map((item) => [
        item.reference,
        item.product_type,
        item.customer_phone,
        String(item.payable_amount),
        item.status,
        item.failure_reason ?? "",
        item.created_at ?? "",
      ]),
    ]);
  };

  const handleExportReconciliation = () => {
    if (!reconciliation) {
      return;
    }

    exportCsv("paylity-daily-reconciliation.csv", [
      ["Metric", "Value"],
      ["Date", reconciliation.date],
      ["Total Transactions", String(reconciliation.total_transactions)],
      ["Successful Payments", String(reconciliation.successful_payments)],
      ["Payment Failed", String(reconciliation.payment_failed)],
      ["Fulfillment Failed", String(reconciliation.fulfillment_failed)],
      ["Fulfilled", String(reconciliation.fulfilled)],
      ["Pending Fulfillment", String(reconciliation.pending_fulfillment)],
      ["Gross Revenue", String(reconciliation.gross_revenue)],
      ["Product Value", String(reconciliation.product_value)],
      ["Convenience Fees", String(reconciliation.convenience_fees)],
      ["Gateway Fees", String(reconciliation.gateway_fees)],
      ["Success Rate", `${reconciliation.success_rate}%`],
    ]);
  };

  const handleExportSettlement = () => {
    if (!settlement) {
      return;
    }

    exportCsv("paylity-settlement-summary.csv", [
      ["Metric", "Value"],
      ["Date From", settlement.date_from],
      ["Date To", settlement.date_to],
      ["Transactions", String(settlement.transactions)],
      ["Collected Amount", String(settlement.collected_amount)],
      ["Product Value", String(settlement.product_value)],
      ["Convenience Fees", String(settlement.convenience_fees)],
      ["Gateway Fees", String(settlement.gateway_fees)],
      ["Estimated Net", String(settlement.estimated_net)],
    ]);
  };

  const handleExportRetries = () => {
    if (!retrySummary) {
      return;
    }

    exportCsv("paylity-retry-summary.csv", [
      ["Reference", "Product", "Phone", "Attempt", "Outcome", "Actor", "Attempted At"],
      ...retrySummary.items.map((item) => [
        item.transaction_reference ?? "",
        item.product_type ?? "",
        item.customer_phone ?? "",
        String(item.attempt_number),
        item.outcome,
        item.actor,
        item.attempted_at ?? "",
      ]),
    ]);
  };

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-4xl space-y-6">
        <header>
          <h1 className="font-display text-3xl font-extrabold text-dark">Reports</h1>
          <p className="mt-2 text-sm text-muted">
            Daily reconciliation, settlement, failed transactions, and retry summaries for soft launch operations.
          </p>
        </header>

        {error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {error}
          </p>
        ) : null}

        <section className="grid gap-4 sm:grid-cols-2">
          <KpiCard label="Today's Revenue" value={loading ? "…" : revenue} />
          <KpiCard label="Today's Transactions" value={loading ? "…" : transactions} />
        </section>

        {reconciliation ? (
          <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <h2 className="font-display text-lg font-extrabold text-dark">Daily Reconciliation</h2>
            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-muted">Successful Payments</dt>
                <dd className="font-semibold text-dark">{reconciliation.successful_payments}</dd>
              </div>
              <div>
                <dt className="text-muted">Pending Fulfillment</dt>
                <dd className="font-semibold text-dark">{reconciliation.pending_fulfillment}</dd>
              </div>
              <div>
                <dt className="text-muted">Payment Failed</dt>
                <dd className="font-semibold text-dark">{reconciliation.payment_failed}</dd>
              </div>
              <div>
                <dt className="text-muted">Success Rate</dt>
                <dd className="font-semibold text-dark">{reconciliation.success_rate}%</dd>
              </div>
            </dl>
            <Button type="button" className="mt-4" variant="outline" onClick={handleExportReconciliation}>
              Export Reconciliation CSV
            </Button>
          </section>
        ) : null}

        {reconciliation?.wallet ? (
          <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <h2 className="font-display text-lg font-extrabold text-dark">VTPass Wallet Summary</h2>
            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-muted">Opening Balance</dt>
                <dd className="font-semibold text-dark">
                  {reconciliation.wallet.opening_balance !== null
                    ? formatNaira(reconciliation.wallet.opening_balance)
                    : "—"}
                </dd>
              </div>
              <div>
                <dt className="text-muted">Closing Balance</dt>
                <dd className="font-semibold text-dark">
                  {reconciliation.wallet.closing_balance !== null
                    ? formatNaira(reconciliation.wallet.closing_balance)
                    : "—"}
                </dd>
              </div>
              <div>
                <dt className="text-muted">Lowest Balance</dt>
                <dd className="font-semibold text-dark">
                  {reconciliation.wallet.lowest_balance !== null
                    ? formatNaira(reconciliation.wallet.lowest_balance)
                    : "—"}
                </dd>
              </div>
              <div>
                <dt className="text-muted">Highest Balance</dt>
                <dd className="font-semibold text-dark">
                  {reconciliation.wallet.highest_balance !== null
                    ? formatNaira(reconciliation.wallet.highest_balance)
                    : "—"}
                </dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-muted">Recharge Events</dt>
                <dd className="font-semibold text-dark">
                  {reconciliation.wallet.recharge_events_available
                    ? `${reconciliation.wallet.recharge_events.length} event(s)`
                    : reconciliation.wallet.recharge_events_note ?? "Not available"}
                </dd>
              </div>
            </dl>
          </section>
        ) : null}

        {settlement ? (
          <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <h2 className="font-display text-lg font-extrabold text-dark">Settlement Summary</h2>
            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-muted">Collected</dt>
                <dd className="font-semibold text-dark">{formatNaira(settlement.collected_amount)}</dd>
              </div>
              <div>
                <dt className="text-muted">Estimated Net</dt>
                <dd className="font-semibold text-dark">{formatNaira(settlement.estimated_net)}</dd>
              </div>
            </dl>
            <Button type="button" className="mt-4" variant="outline" onClick={handleExportSettlement}>
              Export Settlement CSV
            </Button>
          </section>
        ) : null}

        {retrySummary ? (
          <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <h2 className="font-display text-lg font-extrabold text-dark">Retry Summary</h2>
            <p className="mt-2 text-sm text-muted">
              {retrySummary.total_retries} retries today ({retrySummary.successful_retries} successful,{" "}
              {retrySummary.failed_retries} failed).
            </p>
            <Button type="button" className="mt-4" variant="outline" onClick={handleExportRetries}>
              Export Retry CSV
            </Button>
          </section>
        ) : null}

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="font-display text-lg font-extrabold text-dark">CSV Exports</h2>
          <p className="mt-2 text-sm text-muted">
            Download operational reports for offline review or sharing with the launch team.
          </p>
          <div className="mt-4 flex flex-wrap gap-3">
            <Button type="button" onClick={() => void handleExportTransactions()}>
              Export Transactions CSV
            </Button>
            <Button type="button" variant="outline" onClick={() => void handleExportFailed()}>
              Export Failed Transactions CSV
            </Button>
          </div>
        </section>
      </div>
    </PageContainer>
  );
}
