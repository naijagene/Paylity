"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { KpiCard } from "@/components/ui/OpsCards";
import { fetchOpsMonitoring, fetchOpsSummary, searchOpsTransactions } from "@/lib/api/ops";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { exportCsv } from "@/lib/utils/csv";

export function ReportsClient() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [revenue, setRevenue] = useState("—");
  const [transactions, setTransactions] = useState("—");

  useEffect(() => {
    let cancelled = false;

    Promise.all([fetchOpsSummary(), fetchOpsMonitoring()])
      .then(([summary, monitoring]) => {
        if (!cancelled) {
          setRevenue(formatNaira(monitoring.revenue ?? summary.revenue_today ?? 0));
          setTransactions(String(monitoring.transactions ?? summary.total_transactions_today));
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

  const handleExport = async () => {
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

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-4xl space-y-6">
        <header>
          <h1 className="font-display text-3xl font-extrabold text-dark">Reports</h1>
          <p className="mt-2 text-sm text-muted">
            Export today&apos;s operational snapshot for soft launch reporting.
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

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="font-display text-lg font-extrabold text-dark">CSV Export</h2>
          <p className="mt-2 text-sm text-muted">
            Download the latest transaction list as CSV for offline review or sharing with the launch team.
          </p>
          <Button type="button" className="mt-4" onClick={() => void handleExport()}>
            Export Transactions CSV
          </Button>
        </section>
      </div>
    </PageContainer>
  );
}
