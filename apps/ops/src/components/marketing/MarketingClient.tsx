"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { SimpleBarChart } from "@/components/dashboard/SimpleBarChart";
import { AlertCard } from "@/components/ui/AlertCard";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import { CreateCampaignForm } from "@/components/marketing/CreateCampaignForm";
import {
  fetchOpsMarketing,
  fetchOpsVoucherAbuse,
  fetchOpsVoucherAnalytics,
  fetchOpsVoucherCustomerLookup,
  fetchOpsVoucherRedemptions,
  getOpsMarketingExportCsvUrl,
  opsMarketingExportUsage,
  opsMarketingSetCampaignActive,
  type OpsMarketingCampaign,
  type OpsMarketingSnapshot,
  type OpsVoucherAbuseSummary,
  type OpsVoucherAnalytics,
  type OpsVoucherCustomerLookup,
  type OpsVoucherRedemptionLogItem,
} from "@/lib/api/ops";
import { ApiError } from "@/lib/api/client";
import { usePolling } from "@/lib/hooks/usePolling";

type DashboardTab = "overview" | "campaigns" | "redemptions" | "abuse" | "analytics" | "lookup" | "create";

const TABS: Array<{ id: DashboardTab; label: string }> = [
  { id: "overview", label: "Overview" },
  { id: "campaigns", label: "Campaigns" },
  { id: "redemptions", label: "Redemption Log" },
  { id: "abuse", label: "Abuse Monitoring" },
  { id: "analytics", label: "Analytics" },
  { id: "lookup", label: "Customer Lookup" },
  { id: "create", label: "Create Campaign" },
];

function distributionLabel(mode: string): string {
  return mode === "shared_code" ? "Shared campaign code" : "Unique one-time codes";
}

function formatDate(value?: string | null): string {
  if (!value) return "—";
  return new Date(value).toLocaleString();
}

export function MarketingClient() {
  const [activeTab, setActiveTab] = useState<DashboardTab>("overview");
  const [search, setSearch] = useState("");
  const [redemptionSearch, setRedemptionSearch] = useState("");
  const [redemptionStatus, setRedemptionStatus] = useState("");
  const [redemptionSort, setRedemptionSort] = useState("id");
  const [redemptions, setRedemptions] = useState<OpsVoucherRedemptionLogItem[]>([]);
  const [redemptionMeta, setRedemptionMeta] = useState<Record<string, unknown> | null>(null);
  const [abuse, setAbuse] = useState<OpsVoucherAbuseSummary | null>(null);
  const [analytics, setAnalytics] = useState<OpsVoucherAnalytics | null>(null);
  const [lookupQuery, setLookupQuery] = useState("");
  const [lookupResult, setLookupResult] = useState<OpsVoucherCustomerLookup | null>(null);
  const [panelError, setPanelError] = useState<string | null>(null);
  const [panelLoading, setPanelLoading] = useState(false);

  const loadSnapshot = useCallback(async () => fetchOpsMarketing(search || undefined), [search]);
  const snapshot = usePolling({ fetcher: loadSnapshot, intervalMs: 60000 });
  const data = snapshot.data;

  async function loadRedemptions() {
    setPanelLoading(true);
    setPanelError(null);
    try {
      const result = await fetchOpsVoucherRedemptions({
        search: redemptionSearch || undefined,
        status: redemptionStatus || undefined,
        sort_by: redemptionSort,
        sort_dir: "desc",
        per_page: 25,
      });
      setRedemptions(result.data);
      setRedemptionMeta(result.meta ?? null);
    } catch (error) {
      setPanelError(error instanceof ApiError ? error.message : "Unable to load redemption log.");
    } finally {
      setPanelLoading(false);
    }
  }

  async function loadAbuse() {
    setPanelLoading(true);
    setPanelError(null);
    try {
      setAbuse(await fetchOpsVoucherAbuse());
    } catch (error) {
      setPanelError(error instanceof ApiError ? error.message : "Unable to load abuse monitoring.");
    } finally {
      setPanelLoading(false);
    }
  }

  async function loadAnalytics() {
    setPanelLoading(true);
    setPanelError(null);
    try {
      setAnalytics(await fetchOpsVoucherAnalytics());
    } catch (error) {
      setPanelError(error instanceof ApiError ? error.message : "Unable to load analytics.");
    } finally {
      setPanelLoading(false);
    }
  }

  async function runLookup() {
    if (lookupQuery.trim().length < 3) {
      setPanelError("Enter at least 3 characters to search.");
      return;
    }

    setPanelLoading(true);
    setPanelError(null);
    try {
      setLookupResult(await fetchOpsVoucherCustomerLookup(lookupQuery.trim()));
    } catch (error) {
      setPanelError(error instanceof ApiError ? error.message : "Unable to complete customer lookup.");
    } finally {
      setPanelLoading(false);
    }
  }

  useEffect(() => {
    if (activeTab === "redemptions") void loadRedemptions();
    if (activeTab === "abuse") void loadAbuse();
    if (activeTab === "analytics") void loadAnalytics();
  }, [activeTab]);

  const overviewKpis = useMemo(() => buildOverviewKpis(data), [data]);

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-3xl font-extrabold text-dark">Voucher Operations</h1>
            <p className="mt-2 text-sm text-muted">
              Monitor campaigns, redemption capacity, abuse signals, and customer voucher history.
            </p>
            {data?.refreshed_at ? (
              <p className="mt-1 text-xs text-muted">Last refreshed {formatDate(data.refreshed_at)}</p>
            ) : null}
          </div>
          <div className="flex flex-wrap gap-2">
            <input
              className="rounded-xl border border-border px-3 py-2 text-sm"
              placeholder="Search campaigns or codes"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
            <Button type="button" variant="outline" onClick={() => void snapshot.refresh()}>
              Refresh
            </Button>
            <Button type="button" variant="outline" onClick={() => void opsMarketingExportUsage()}>
              Export JSON
            </Button>
          </div>
        </header>

        <div className="flex flex-wrap gap-2">
          {TABS.map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={`rounded-xl px-3 py-2 text-sm font-semibold transition-colors ${
                activeTab === tab.id ? "bg-success text-white" : "border border-border bg-card text-muted hover:text-dark"
              }`}
            >
              {tab.label}
            </button>
          ))}
        </div>

        {snapshot.loading && !data ? <p className="text-sm text-muted">Loading voucher operations data…</p> : null}
        {snapshot.error ? (
          <AlertCard
            severity="critical"
            message={typeof snapshot.error === "string" ? snapshot.error : "Unable to load dashboard."}
          />
        ) : null}
        {panelError ? <AlertCard severity="critical" message={panelError} /> : null}

        {activeTab === "overview" && data ? <OverviewPanel kpis={overviewKpis} data={data} /> : null}
        {activeTab === "campaigns" && data ? (
          <CampaignsPanel campaigns={data.campaigns ?? []} onToggleActive={() => void snapshot.refresh()} />
        ) : null}
        {activeTab === "redemptions" ? (
          <RedemptionsPanel
            rows={redemptions}
            meta={redemptionMeta}
            loading={panelLoading}
            search={redemptionSearch}
            status={redemptionStatus}
            sortBy={redemptionSort}
            onSearchChange={setRedemptionSearch}
            onStatusChange={setRedemptionStatus}
            onSortChange={setRedemptionSort}
            onApply={() => void loadRedemptions()}
          />
        ) : null}
        {activeTab === "abuse" ? <AbusePanel abuse={abuse} loading={panelLoading} /> : null}
        {activeTab === "analytics" ? <AnalyticsPanel analytics={analytics} loading={panelLoading} /> : null}
        {activeTab === "lookup" ? (
          <LookupPanel
            query={lookupQuery}
            result={lookupResult}
            loading={panelLoading}
            onQueryChange={setLookupQuery}
            onSearch={() => void runLookup()}
          />
        ) : null}
        {activeTab === "create" ? <CreateCampaignForm onCreated={() => void snapshot.refresh()} /> : null}
      </div>
    </PageContainer>
  );
}

function buildOverviewKpis(data: OpsMarketingSnapshot | null) {
  const kpis = data?.kpis;
  return [
    { label: "Total Campaigns", value: String(kpis?.total_campaigns ?? 0) },
    { label: "Active Campaigns", value: String(kpis?.active_campaigns ?? kpis?.active ?? 0) },
    { label: "Expired Campaigns", value: String(kpis?.expired_campaigns ?? kpis?.expired ?? 0) },
    { label: "Shared Campaigns", value: String(kpis?.shared_campaigns ?? 0) },
    { label: "Unique Campaigns", value: String(kpis?.unique_campaigns ?? 0) },
    { label: "Generated Codes", value: String(kpis?.generated_codes ?? kpis?.generated ?? 0) },
    { label: "Successful Redemptions", value: String(kpis?.successful_redemptions ?? kpis?.redeemed ?? 0) },
    { label: "Remaining Capacity", value: String(kpis?.remaining_capacity ?? kpis?.remaining ?? 0) },
    { label: "Blocked Attempts", value: String(kpis?.blocked_attempts ?? 0) },
    { label: "Expired Reservations", value: String(kpis?.expired_reservations ?? 0) },
  ];
}

function OverviewPanel({ kpis, data }: { kpis: Array<{ label: string; value: string }>; data: OpsMarketingSnapshot }) {
  return (
    <>
      <SectionCard title="Dashboard KPIs">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          {kpis.map((kpi) => (
            <KpiCard key={kpi.label} label={kpi.label} value={kpi.value} />
          ))}
        </div>
      </SectionCard>
      <SectionCard title="Engagement">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <KpiCard label="Review Rate" value={`${data.kpis.review_rate_pct}%`} />
          <KpiCard label="Share Rate" value={`${data.kpis.share_rate_pct}%`} />
          <KpiCard label="Reviews" value={String(data.reviews.count)} />
          <KpiCard label="Avg Rating" value={data.reviews.average_rating?.toFixed(1) ?? "—"} />
        </div>
      </SectionCard>
    </>
  );
}

function CampaignsPanel({
  campaigns,
  onToggleActive,
}: {
  campaigns: OpsMarketingCampaign[];
  onToggleActive: () => void;
}) {
  return (
    <SectionCard title="Campaigns">
      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border text-muted">
              <th className="px-2 py-2">Campaign</th>
              <th className="px-2 py-2">Mode</th>
              <th className="px-2 py-2">Capacity</th>
              <th className="px-2 py-2">Redeemed</th>
              <th className="px-2 py-2">Remaining</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {campaigns.map((campaign) => (
              <tr key={campaign.id} className="border-b border-border/60">
                <td className="px-2 py-3">
                  <Link href={`/marketing/campaigns/${campaign.id}`} className="font-semibold text-success hover:underline">
                    {campaign.name}
                  </Link>
                  <p className="text-xs text-muted">₦{campaign.amount.toLocaleString("en-NG")}</p>
                </td>
                <td className="px-2 py-3">{distributionLabel(campaign.distribution_mode)}</td>
                <td className="px-2 py-3">
                  {campaign.distribution_mode === "shared_code"
                    ? campaign.max_redemptions ?? 0
                    : campaign.generated_count}
                </td>
                <td className="px-2 py-3">{campaign.redeemed_count}</td>
                <td className="px-2 py-3">{campaign.remaining_capacity ?? 0}</td>
                <td className="px-2 py-3">
                  <StatusBadge variant={campaign.active ? "success" : "failed"} label={campaign.active ? "Active" : "Paused"} />
                </td>
                <td className="px-2 py-3">
                  <div className="flex flex-wrap gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      className="!px-2 !py-1 text-xs"
                      onClick={() => void opsMarketingSetCampaignActive(campaign.id, !campaign.active).then(onToggleActive)}
                    >
                      {campaign.active ? "Pause" : "Resume"}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      className="!px-2 !py-1 text-xs"
                      onClick={() => void opsMarketingExportUsage(campaign.id)}
                    >
                      Export JSON
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      className="!px-2 !py-1 text-xs"
                      onClick={() => window.open(getOpsMarketingExportCsvUrl(campaign.id), "_blank")}
                    >
                      Export CSV
                    </Button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </SectionCard>
  );
}

function RedemptionsPanel({
  rows,
  meta,
  loading,
  search,
  status,
  sortBy,
  onSearchChange,
  onStatusChange,
  onSortChange,
  onApply,
}: {
  rows: OpsVoucherRedemptionLogItem[];
  meta: Record<string, unknown> | null;
  loading: boolean;
  search: string;
  status: string;
  sortBy: string;
  onSearchChange: (value: string) => void;
  onStatusChange: (value: string) => void;
  onSortChange: (value: string) => void;
  onApply: () => void;
}) {
  return (
    <SectionCard title="Redemption Log">
      <div className="mb-4 grid gap-3 md:grid-cols-4">
        <input className="rounded-xl border border-border px-3 py-2 text-sm" placeholder="Phone, code, or reference" value={search} onChange={(e) => onSearchChange(e.target.value)} />
        <select className="rounded-xl border border-border px-3 py-2 text-sm" value={status} onChange={(e) => onStatusChange(e.target.value)}>
          <option value="">All statuses</option>
          <option value="reserved">Reserved</option>
          <option value="redeemed">Redeemed</option>
          <option value="released">Released</option>
          <option value="expired">Expired</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <select className="rounded-xl border border-border px-3 py-2 text-sm" value={sortBy} onChange={(e) => onSortChange(e.target.value)}>
          <option value="id">Newest</option>
          <option value="reserved_at">Reserved at</option>
          <option value="redeemed_at">Redeemed at</option>
          <option value="status">Status</option>
        </select>
        <Button type="button" onClick={onApply} disabled={loading}>
          {loading ? "Loading…" : "Apply Filters"}
        </Button>
      </div>
      <div className="overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead>
            <tr className="border-b border-border text-muted">
              <th className="px-2 py-2">Voucher</th>
              <th className="px-2 py-2">Campaign</th>
              <th className="px-2 py-2">Phone</th>
              <th className="px-2 py-2">Reference</th>
              <th className="px-2 py-2">Status</th>
              <th className="px-2 py-2">Reserved</th>
              <th className="px-2 py-2">Redeemed</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-b border-border/60">
                <td className="px-2 py-3 font-mono text-xs">{row.voucher_code}</td>
                <td className="px-2 py-3">{row.campaign_name}</td>
                <td className="px-2 py-3">{row.customer_phone}</td>
                <td className="px-2 py-3">
                  {row.reference ? (
                    <Link href={`/transactions/${row.reference}`} className="text-success hover:underline">
                      {row.reference}
                    </Link>
                  ) : (
                    "—"
                  )}
                </td>
                <td className="px-2 py-3">
                  <StatusBadge
                    variant={row.status === "redeemed" ? "success" : row.status === "reserved" ? "processing" : "info"}
                    label={row.status}
                  />
                </td>
                <td className="px-2 py-3">{formatDate(row.reserved_at)}</td>
                <td className="px-2 py-3">{formatDate(row.redeemed_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {meta ? <p className="mt-3 text-xs text-muted">Showing {rows.length} of {String(meta.total ?? rows.length)} redemptions</p> : null}
    </SectionCard>
  );
}

function AbusePanel({ abuse, loading }: { abuse: OpsVoucherAbuseSummary | null; loading: boolean }) {
  if (loading && !abuse) return <p className="text-sm text-muted">Loading abuse monitoring…</p>;
  if (!abuse) return null;

  const summary = [
    { label: "Phone blocked", value: abuse.summary.phone_blocked },
    { label: "Device blocked", value: abuse.summary.device_blocked },
    { label: "Email blocked", value: abuse.summary.email_blocked },
    { label: "Invalid voucher", value: abuse.summary.invalid_voucher },
    { label: "Expired voucher", value: abuse.summary.expired_voucher },
    { label: "Capacity exceeded", value: abuse.summary.capacity_exceeded },
    { label: "Reservation expired", value: abuse.summary.reservation_expired },
  ];

  return (
    <>
      <SectionCard title={`Abuse Summary (${abuse.window_days} days)`}>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {summary.map((item) => (
            <KpiCard key={item.label} label={item.label} value={String(item.value)} />
          ))}
        </div>
      </SectionCard>
      <SectionCard title="Blocked Attempt Trends">
        <SimpleBarChart
          items={abuse.blocked_trend.map((point) => ({ label: point.date, value: point.total }))}
        />
      </SectionCard>
      <SectionCard title="Recent Abuse Events">
        <div className="space-y-2">
          {abuse.recent_events.map((event) => (
            <div key={event.id} className="rounded-xl border border-border px-3 py-2 text-sm">
              <p className="font-semibold text-dark">{event.event_type}</p>
              <p className="text-muted">
                {event.voucher_code ?? "—"} · {formatDate(event.occurred_at)} · {event.actor ?? "system"}
              </p>
            </div>
          ))}
        </div>
      </SectionCard>
    </>
  );
}

function AnalyticsPanel({ analytics, loading }: { analytics: OpsVoucherAnalytics | null; loading: boolean }) {
  if (loading && !analytics) return <p className="text-sm text-muted">Loading analytics…</p>;
  if (!analytics) return null;

  return (
    <>
      <SectionCard title="Daily Redemptions">
        <SimpleBarChart items={analytics.daily_redemptions.map((point) => ({ label: point.date, value: point.total }))} />
      </SectionCard>
      <SectionCard title="Campaign Usage">
        <SimpleBarChart
          items={analytics.campaign_usage.map((campaign) => ({
            label: campaign.name,
            value: campaign.redeemed_count,
          }))}
        />
      </SectionCard>
      <SectionCard title="Network Distribution">
        <SimpleBarChart items={analytics.network_distribution.map((item) => ({ label: item.label, value: item.value }))} />
      </SectionCard>
      <SectionCard title="Blocked Attempt Trends">
        <SimpleBarChart items={analytics.blocked_trend.map((point) => ({ label: point.date, value: point.total }))} />
      </SectionCard>
    </>
  );
}

function LookupPanel({
  query,
  result,
  loading,
  onQueryChange,
  onSearch,
}: {
  query: string;
  result: OpsVoucherCustomerLookup | null;
  loading: boolean;
  onQueryChange: (value: string) => void;
  onSearch: () => void;
}) {
  return (
    <>
      <SectionCard title="Customer Lookup">
        <div className="flex flex-wrap gap-3">
          <input
            className="min-w-[280px] flex-1 rounded-xl border border-border px-3 py-2 text-sm"
            placeholder="Phone, voucher code, or transaction reference"
            value={query}
            onChange={(event) => onQueryChange(event.target.value)}
          />
          <Button type="button" onClick={onSearch} disabled={loading}>
            {loading ? "Searching…" : "Search"}
          </Button>
        </div>
      </SectionCard>
      {result ? (
        <>
          <SectionCard title="Redemption History">
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-border text-muted">
                    <th className="px-2 py-2">Voucher</th>
                    <th className="px-2 py-2">Campaign</th>
                    <th className="px-2 py-2">Phone</th>
                    <th className="px-2 py-2">Status</th>
                    <th className="px-2 py-2">Reference</th>
                  </tr>
                </thead>
                <tbody>
                  {result.redemptions.map((row) => (
                    <tr key={row.id} className="border-b border-border/60">
                      <td className="px-2 py-3 font-mono text-xs">{row.voucher_code}</td>
                      <td className="px-2 py-3">{row.campaign_name}</td>
                      <td className="px-2 py-3">{row.customer_phone}</td>
                      <td className="px-2 py-3">{row.status}</td>
                      <td className="px-2 py-3">{row.reference ?? "—"}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </SectionCard>
          <SectionCard title="Transaction History">
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead>
                  <tr className="border-b border-border text-muted">
                    <th className="px-2 py-2">Reference</th>
                    <th className="px-2 py-2">Status</th>
                    <th className="px-2 py-2">Phone</th>
                    <th className="px-2 py-2">Voucher</th>
                    <th className="px-2 py-2">Created</th>
                  </tr>
                </thead>
                <tbody>
                  {result.transactions.map((transaction) => (
                    <tr key={transaction.reference} className="border-b border-border/60">
                      <td className="px-2 py-3">
                        <Link href={`/transactions/${transaction.reference}`} className="text-success hover:underline">
                          {transaction.reference}
                        </Link>
                      </td>
                      <td className="px-2 py-3">{transaction.status}</td>
                      <td className="px-2 py-3">{transaction.customer_phone}</td>
                      <td className="px-2 py-3">{transaction.voucher_code ?? "—"}</td>
                      <td className="px-2 py-3">{formatDate(transaction.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </SectionCard>
        </>
      ) : null}
    </>
  );
}
