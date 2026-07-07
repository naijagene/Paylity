"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import { fetchFeatureFlags } from "@/lib/api/admin";
import { fetchPublicHealth } from "@/lib/api/health";
import { fetchOpsMonitoring, fetchOpsSummary, searchOpsTransactions } from "@/lib/api/ops";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { formatNaira } from "@/lib/checkout/formatNaira";
import {
  calculateSuccessRate,
  healthClasses,
  healthLabel,
  mapApiHealth,
  mapDatabaseHealth,
  mapFeatureHealth,
  type HealthIndicator,
} from "@/lib/utils/health";
import { exportCsv } from "@/lib/utils/csv";

type PlatformHealth = {
  label: string;
  indicator: HealthIndicator;
  detail: string;
};

export function ExecutiveDashboardClient() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [kpis, setKpis] = useState({
    revenue: "—",
    transactions: "—",
    pending: "—",
    failed: "—",
    successRate: "—",
    avgFulfillment: "—",
  });
  const [healthCards, setHealthCards] = useState<PlatformHealth[]>([]);

  useEffect(() => {
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);

      try {
        const [summary, monitoring, health, flags, failedItems] = await Promise.all([
          fetchOpsSummary(),
          fetchOpsMonitoring(),
          fetchPublicHealth(),
          fetchFeatureFlags(),
          searchOpsTransactions({ status: "failed", per_page: 1 }),
        ]);

        if (cancelled) {
          return;
        }

        const paystack = flags.find((flag) => flag.key === "paystack");
        const vtpass = flags.find((flag) => flag.key === "vtpass");

        setKpis({
          revenue: formatNaira(monitoring.revenue ?? summary.revenue_today ?? 0),
          transactions: String(monitoring.transactions ?? summary.total_transactions_today),
          pending: String(monitoring.pending ?? summary.pending_fulfillment),
          failed: String(monitoring.failures ?? summary.failed_today),
          successRate: calculateSuccessRate(
            summary.successful_payments_today,
            summary.total_transactions_today,
          ),
          avgFulfillment:
            monitoring.average_fulfillment_seconds != null
              ? `${monitoring.average_fulfillment_seconds}s`
              : "—",
        });

        setHealthCards([
          {
            label: "API",
            indicator: mapApiHealth(health.status),
            detail: health.environment ?? "PAYLITY API",
          },
          {
            label: "Database",
            indicator: mapDatabaseHealth(health.checks?.database),
            detail: health.checks?.database === "ok" ? "Connected" : "Check connection",
          },
          {
            label: "Paystack",
            indicator: mapFeatureHealth(Boolean(paystack?.enabled)),
            detail: paystack?.enabled ? "Integration enabled" : "Integration disabled",
          },
          {
            label: "VTPass",
            indicator: mapFeatureHealth(Boolean(vtpass?.enabled)),
            detail: vtpass?.enabled ? "Integration enabled" : "Integration disabled",
          },
          {
            label: "Queue",
            indicator: "warning",
            detail: "Queue monitoring not configured yet",
          },
        ]);

        void failedItems;
      } catch (err) {
        if (!cancelled) {
          if (err instanceof ApiOfflineError) {
            setError("Network unavailable. Check the API server and try again.");
          } else if (err instanceof ApiError) {
            setError(err.message);
          } else {
            setError("Unable to load executive dashboard.");
          }
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void load();

    return () => {
      cancelled = true;
    };
  }, []);

  const handleDailyReport = async () => {
    const result = await searchOpsTransactions({ per_page: 100 });
    exportCsv("paylity-daily-report.csv", [
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
      <div className="mx-auto w-full max-w-7xl space-y-8">
        <header>
          <p className="text-sm font-semibold uppercase tracking-wide text-success">
            Executive Dashboard
          </p>
          <h1 className="mt-2 font-display text-3xl font-extrabold tracking-tight text-dark">
            Soft Launch Operations
          </h1>
          <p className="mt-2 text-sm text-muted">
            Monitor today&apos;s performance, platform health, and jump into common operator actions.
          </p>
        </header>

        {error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {error}
          </p>
        ) : null}

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <KpiCard label="Today's Revenue" value={loading ? "…" : kpis.revenue} />
          <KpiCard label="Today's Transactions" value={loading ? "…" : kpis.transactions} />
          <KpiCard label="Pending Transactions" value={loading ? "…" : kpis.pending} />
          <KpiCard label="Failed Transactions" value={loading ? "…" : kpis.failed} />
          <KpiCard label="Success Rate" value={loading ? "…" : kpis.successRate} />
          <KpiCard label="Average Fulfillment Time" value={loading ? "…" : kpis.avgFulfillment} />
        </section>

        <SectionCard title="Platform Health">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {healthCards.map((card) => (
              <div
                key={card.label}
                className={`rounded-2xl border p-4 ${healthClasses(card.indicator)}`}
              >
                <p className="text-sm font-semibold">{card.label}</p>
                <p className="mt-2 text-lg font-extrabold">{healthLabel(card.indicator)}</p>
                <p className="mt-1 text-xs opacity-80">{card.detail}</p>
              </div>
            ))}
          </div>
        </SectionCard>

        <SectionCard title="Quick Actions">
          <div className="flex flex-wrap gap-3">
            <Button href="/transactions">View Transactions</Button>
            <Button href="/transactions?status=failed" variant="outline">
              Retry Failed
            </Button>
            <Button href="/platform" variant="outline">
              Platform Settings
            </Button>
            <Button href="/platform?tab=flags" variant="outline">
              Feature Flags
            </Button>
            <Button type="button" variant="secondary" onClick={() => void handleDailyReport()}>
              Download Daily Report
            </Button>
          </div>
        </SectionCard>
      </div>
    </PageContainer>
  );
}
