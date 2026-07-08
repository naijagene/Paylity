"use client";

import { memo, useCallback, useMemo } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { AlertCenter } from "@/components/dashboard/AlertCenter";
import { LiveTransactionFeed } from "@/components/dashboard/LiveTransactionFeed";
import { SimpleBarChart } from "@/components/dashboard/SimpleBarChart";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import { fetchOpsDashboard, searchOpsTransactions } from "@/lib/api/ops";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { usePolling } from "@/lib/hooks/usePolling";
import {
  buildProductChartData,
  buildRevenueChartData,
  formatVtpassBalance,
  formatVtpassEnvironment,
  type LiveFeedItem,
  type OpsDashboardSnapshot,
} from "@/lib/utils/dashboard";
import {
  healthClasses,
  healthLabel,
  mapApiHealth,
  mapDatabaseHealth,
} from "@/lib/utils/health";
import { exportCsv } from "@/lib/utils/csv";

const POLL_INTERVAL_MS = 5000;

function mapProviderIndicator(status: string): "healthy" | "warning" | "offline" {
  if (status === "ok" || status === "skipped") {
    return "healthy";
  }

  if (status === "degraded" || status === "warning") {
    return "warning";
  }

  return "offline";
}

const ProviderHealthGrid = memo(function ProviderHealthGrid({
  providers,
}: {
  providers: OpsDashboardSnapshot["providers"];
}) {
  const cards = [
    { label: "Paystack", status: providers.paystack?.status ?? "unknown" },
    { label: "VTPass", status: providers.vtpass?.status ?? "unknown" },
    { label: "Database", status: providers.database?.status ?? "unknown" },
    { label: "Cache", status: providers.cache?.status ?? "unknown" },
    {
      label: "Queue",
      status: providers.queue?.status ?? "unknown",
      detail: `pending ${providers.queue?.pending_jobs ?? 0} · failed ${providers.queue?.failed_jobs ?? 0}`,
    },
    { label: "Mail", status: providers.mail?.status ?? "unknown" },
  ];

  return (
    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      {cards.map((card) => {
        const indicator =
          card.label === "Database" || card.label === "Cache"
            ? mapDatabaseHealth(card.status)
            : mapProviderIndicator(card.status);

        return (
          <div
            key={card.label}
            className={`rounded-2xl border p-4 ${healthClasses(indicator)}`}
          >
            <p className="text-sm font-semibold">{card.label}</p>
            <p className="mt-2 text-lg font-extrabold">{healthLabel(indicator)}</p>
            <p className="mt-1 text-xs opacity-80">{card.detail ?? card.status}</p>
          </div>
        );
      })}
    </div>
  );
});

export function ExecutiveDashboardClient() {
  const loadDashboard = useCallback(async () => fetchOpsDashboard(), []);
  const loadFeed = useCallback(async () => {
    const result = await searchOpsTransactions({ per_page: 15 });
    return result.items as LiveFeedItem[];
  }, []);

  const dashboard = usePolling({
    fetcher: loadDashboard,
    intervalMs: POLL_INTERVAL_MS,
  });

  const feed = usePolling({
    fetcher: loadFeed,
    intervalMs: POLL_INTERVAL_MS,
  });

  const snapshot = dashboard.data;
  const revenueChart = useMemo(
    () => (snapshot ? buildRevenueChartData(snapshot.revenue) : []),
    [snapshot],
  );
  const productChart = useMemo(
    () => (snapshot ? buildProductChartData(snapshot.transactions) : []),
    [snapshot],
  );

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

  const loading = dashboard.loading && !snapshot;
  const apiIndicator = mapApiHealth(snapshot?.executive.api_health);

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-8">
        <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-success">
              Operations Command Center
            </p>
            <h1 className="mt-2 font-display text-3xl font-extrabold tracking-tight text-dark">
              Live Revenue &amp; Monitoring
            </h1>
            <p className="mt-2 text-sm text-muted">
              Real-time PAYLITY operations dashboard with 5-second auto-refresh.
            </p>
          </div>
          <div className="text-sm text-muted">
            <p>
              {dashboard.paused ? "Polling paused (tab inactive)" : "Live polling active"}
            </p>
            <p>
              Updated{" "}
              {dashboard.lastUpdated
                ? new Date(dashboard.lastUpdated).toLocaleTimeString("en-NG")
                : "—"}
            </p>
          </div>
        </header>

        {dashboard.error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {dashboard.error}
          </p>
        ) : null}

        <SectionCard title="Alert Center">
          <AlertCenter alerts={snapshot?.alerts ?? []} />
        </SectionCard>

        <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <KpiCard
            label="Today's Revenue"
            value={loading ? "…" : formatNaira(snapshot?.executive.revenue_today ?? 0)}
          />
          <KpiCard
            label="Today's Transactions"
            value={loading ? "…" : String(snapshot?.executive.transactions_today ?? 0)}
          />
          <KpiCard
            label="Success Rate"
            value={loading ? "…" : `${snapshot?.executive.success_rate ?? 0}%`}
          />
          <KpiCard
            label="Pending"
            value={loading ? "…" : String(snapshot?.executive.pending ?? 0)}
          />
          <KpiCard
            label="Failed"
            value={loading ? "…" : String(snapshot?.executive.failed ?? 0)}
          />
          <KpiCard
            label="Average Transaction"
            value={
              loading ? "…" : formatNaira(snapshot?.executive.average_transaction ?? 0)
            }
          />
          <KpiCard
            label="Queue Size"
            value={loading ? "…" : String(snapshot?.executive.queue_size ?? 0)}
          />
          <KpiCard
            label="API Health"
            value={loading ? "…" : healthLabel(apiIndicator)}
            hint={snapshot?.executive.api_health}
          />
        </section>

        <div className="grid gap-6 xl:grid-cols-2">
          <SectionCard title="Revenue Analytics">
            <div className="mb-4 grid gap-3 sm:grid-cols-2">
              <KpiCard
                label="Total Revenue"
                value={loading ? "…" : formatNaira(snapshot?.revenue.today.total_revenue ?? 0)}
              />
              <KpiCard
                label="Net Revenue"
                value={loading ? "…" : formatNaira(snapshot?.revenue.today.net_revenue ?? 0)}
              />
              <KpiCard
                label="Platform Fees"
                value={loading ? "…" : formatNaira(snapshot?.revenue.today.platform_fees ?? 0)}
              />
              <KpiCard
                label="Gateway Charges"
                value={loading ? "…" : formatNaira(snapshot?.revenue.today.gateway_charges ?? 0)}
              />
            </div>
            <SimpleBarChart
              items={revenueChart}
              formatValue={(value) => formatNaira(value)}
            />
          </SectionCard>

          <SectionCard title="Transaction Analytics">
            <SimpleBarChart items={productChart} />
          </SectionCard>
        </div>

        <div className="grid gap-6 xl:grid-cols-2">
          <SectionCard title="Provider Health">
            {snapshot ? <ProviderHealthGrid providers={snapshot.providers} /> : "…"}
          </SectionCard>

          <SectionCard title="VTPass Live Readiness">
            {snapshot?.vtpass ? (
              <div className="space-y-4">
                <dl className="grid gap-4 sm:grid-cols-2">
                  <div>
                    <dt className="text-sm text-muted">Environment</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {formatVtpassEnvironment(snapshot.vtpass.environment)}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Status</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.vtpass.status}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Wallet Balance</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {formatVtpassBalance(snapshot.vtpass.balance)}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Auto-fulfill</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.vtpass.auto_fulfill ? "Enabled" : "Disabled"}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Live Safety Mode</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.vtpass.live_safety_mode
                        ? `Active (max ₦${snapshot.vtpass.live_test_max_amount})`
                        : "Off"}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Host</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.vtpass.base_url_host}
                    </dd>
                  </div>
                </dl>
                <div>
                  <p className="mb-2 text-sm font-semibold text-dark">Product Readiness</p>
                  <div className="grid gap-2 sm:grid-cols-3">
                    {Object.entries(snapshot.vtpass.product_readiness).map(
                      ([product, readiness]) => (
                        <div
                          key={product}
                          className={`rounded-xl border px-3 py-2 text-sm ${
                            readiness.ready
                              ? "border-success/30 bg-success/5 text-success"
                              : "border-warning/30 bg-warning/5 text-warning"
                          }`}
                        >
                          <p className="font-semibold capitalize">{product}</p>
                          <p>{readiness.ready ? "Ready" : "Not ready"}</p>
                        </div>
                      ),
                    )}
                  </div>
                </div>
              </div>
            ) : (
              "…"
            )}
          </SectionCard>
        </div>

        <SectionCard title="Payment Reliability">
          {snapshot?.reliability ? (
            <div className="grid gap-6 lg:grid-cols-2">
              <div>
                <p className="mb-3 text-sm font-semibold text-dark">Webhook Health (24h)</p>
                <dl className="grid gap-3 sm:grid-cols-2">
                  <div>
                    <dt className="text-sm text-muted">Processed</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.reliability.webhooks.processed_24h}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Failed</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.reliability.webhooks.failed_24h}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Duplicates</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.reliability.webhooks.duplicate_24h}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Pending</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.reliability.webhooks.pending}
                    </dd>
                  </div>
                </dl>
              </div>
              <div>
                <p className="mb-3 text-sm font-semibold text-dark">Queues</p>
                <dl className="grid gap-3 sm:grid-cols-2">
                  <div>
                    <dt className="text-sm text-muted">Retry Due</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.reliability.retry_queue.due_now}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Retry Scheduled</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.reliability.retry_queue.scheduled}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Manual Review</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.reliability.manual_review.count}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-sm text-muted">Stale Payments</dt>
                    <dd className="text-lg font-extrabold text-dark">
                      {snapshot.reliability.reconciliation.stale_payment_pending}
                    </dd>
                  </div>
                </dl>
              </div>
            </div>
          ) : (
            "…"
          )}
        </SectionCard>

        <div className="grid gap-6 xl:grid-cols-2">
          <SectionCard title="Fraud Monitoring">
            <dl className="grid gap-4 sm:grid-cols-2">
              <div>
                <dt className="text-sm text-muted">OTP Failures Today</dt>
                <dd className="text-xl font-extrabold text-dark">
                  {loading ? "…" : snapshot?.fraud.otp_failures_today ?? 0}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-muted">Failed Verifications</dt>
                <dd className="text-xl font-extrabold text-dark">
                  {loading ? "…" : snapshot?.fraud.failed_verifications ?? 0}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-muted">Blocked Transactions</dt>
                <dd className="text-xl font-extrabold text-dark">
                  {loading ? "…" : snapshot?.fraud.blocked_transactions ?? 0}
                </dd>
              </div>
              <div>
                <dt className="text-sm text-muted">Daily Limit Hits</dt>
                <dd className="text-xl font-extrabold text-dark">
                  {loading ? "…" : snapshot?.fraud.daily_limit_hits ?? 0}
                </dd>
              </div>
            </dl>
          </SectionCard>
        </div>

        <SectionCard
          title="Live Transaction Feed"
          action={
            <span className="text-xs font-semibold uppercase tracking-wide text-success">
              Auto-refresh 5s
            </span>
          }
        >
          <LiveTransactionFeed items={feed.data ?? []} loading={feed.loading} />
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
            <Button href="/monitoring" variant="outline">
              Monitoring
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
