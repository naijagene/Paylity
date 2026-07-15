"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { AlertCard } from "@/components/ui/AlertCard";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import {
  fetchOpsVoucherCampaignDetail,
  formatExpiresAtForBackend,
  getOpsMarketingExportCsvUrl,
  opsMarketingExportUsage,
  opsMarketingExtendExpiry,
  opsMarketingIncreaseCapacity,
  opsMarketingSetCampaignActive,
  type OpsVoucherCampaignDetail,
} from "@/lib/api/ops";
import { ApiError } from "@/lib/api/client";

function formatDate(value?: string | null): string {
  if (!value) return "—";
  return new Date(value).toLocaleString();
}

export function CampaignDetailClient({ campaignId }: { campaignId: number }) {
  const [detail, setDetail] = useState<OpsVoucherCampaignDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [newExpiry, setNewExpiry] = useState("");
  const [newCapacity, setNewCapacity] = useState<number | "">("");
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const loadDetail = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      setDetail(await fetchOpsVoucherCampaignDetail(campaignId));
    } catch (loadError) {
      setError(loadError instanceof ApiError ? loadError.message : "Unable to load campaign detail.");
    } finally {
      setLoading(false);
    }
  }, [campaignId]);

  useEffect(() => {
    void loadDetail();
  }, [loadDetail]);

  if (loading && !detail) return <PageContainer className="py-8"><p className="text-sm text-muted">Loading campaign…</p></PageContainer>;
  if (error) return <PageContainer className="py-8"><AlertCard severity="critical" message={error} /></PageContainer>;
  if (!detail) return null;

  const campaign = detail.campaign;

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="text-sm text-muted"><Link href="/marketing" className="text-success hover:underline">Voucher Operations</Link> / Campaign</p>
            <h1 className="font-display text-3xl font-extrabold text-dark">{campaign.name}</h1>
            <p className="mt-2 text-sm text-muted">
              ₦{campaign.amount.toLocaleString("en-NG")} · {campaign.distribution_mode === "shared_code" ? "Shared campaign code" : "Unique one-time codes"}
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            <StatusBadge variant={campaign.active ? "success" : "failed"} label={campaign.active ? "Active" : "Paused"} />
            <Button type="button" variant="outline" onClick={() => void loadDetail()}>Refresh</Button>
          </div>
        </header>

        {actionMessage ? <AlertCard severity="success" message={actionMessage} /> : null}

        <SectionCard title="Capacity Progress">
          <div className="mb-3 flex items-center justify-between text-sm">
            <span>{detail.statistics.used_capacity} used of {detail.statistics.total_capacity}</span>
            <span>{detail.statistics.progress_pct}%</span>
          </div>
          <div className="h-3 rounded-full bg-slate-100">
            <div className="h-3 rounded-full bg-success" style={{ width: `${detail.statistics.progress_pct}%` }} />
          </div>
          <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <KpiCard label="Reserved" value={String(detail.statistics.reserved)} />
            <KpiCard label="Redeemed" value={String(detail.statistics.redeemed)} />
            <KpiCard label="Released" value={String(detail.statistics.released)} />
            <KpiCard label="Expired" value={String(detail.statistics.expired)} />
            <KpiCard label="Remaining" value={String(campaign.remaining_capacity ?? 0)} />
          </div>
        </SectionCard>

        <SectionCard title="Campaign Metadata">
          <div className="grid gap-2 text-sm sm:grid-cols-2">
            <p>Network: {campaign.network ?? "All networks"}</p>
            <p>Expiry: {formatDate(campaign.expires_at)}</p>
            <p>Generated codes: {campaign.generated_count}</p>
            <p>Max redemptions: {campaign.max_redemptions ?? "—"}</p>
            <p>Created by: {campaign.created_by ?? "—"}</p>
            <p>Created at: {formatDate(campaign.created_at)}</p>
          </div>
        </SectionCard>

        <SectionCard title="Restriction Settings">
          <div className="grid gap-2 text-sm sm:grid-cols-2">
            <p>One per phone: {detail.restrictions.one_per_phone ? "Yes" : "No"}</p>
            <p>One per email: {detail.restrictions.one_per_email ? "Yes" : "No"}</p>
            <p>One per device: {detail.restrictions.one_per_device ? "Yes" : "No"}</p>
            <p>Reservation timeout: {detail.restrictions.reservation_timeout_minutes} minutes</p>
          </div>
        </SectionCard>

        <SectionCard title="Campaign Actions">
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <label className="block text-sm font-semibold text-dark">Extend expiry</label>
              <input type="datetime-local" className="w-full rounded-xl border border-border px-3 py-2 text-sm" value={newExpiry} onChange={(e) => setNewExpiry(e.target.value)} />
              <Button type="button" variant="outline" onClick={() => void opsMarketingExtendExpiry(campaignId, formatExpiresAtForBackend(newExpiry) ?? "").then((updated) => { setDetail(updated); setActionMessage("Campaign expiry extended."); })}>
                Extend expiry
              </Button>
            </div>
            {campaign.distribution_mode === "shared_code" ? (
              <div className="space-y-2">
                <label className="block text-sm font-semibold text-dark">Increase capacity</label>
                <input type="number" min={(campaign.max_redemptions ?? 0) + 1} className="w-full rounded-xl border border-border px-3 py-2 text-sm" value={newCapacity} onChange={(e) => setNewCapacity(e.target.value === "" ? "" : Number(e.target.value))} />
                <Button type="button" variant="outline" onClick={() => typeof newCapacity === "number" ? void opsMarketingIncreaseCapacity(campaignId, newCapacity).then((updated) => { setDetail(updated); setActionMessage("Campaign capacity updated."); }) : undefined}>
                  Increase capacity
                </Button>
              </div>
            ) : null}
          </div>
          <div className="mt-4 flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={() => void opsMarketingSetCampaignActive(campaignId, !campaign.active).then(() => loadDetail())}>
              {campaign.active ? "Pause campaign" : "Resume campaign"}
            </Button>
            <Button type="button" variant="outline" onClick={() => void opsMarketingExportUsage(campaignId)}>Export JSON</Button>
            <Button type="button" variant="outline" onClick={() => window.open(getOpsMarketingExportCsvUrl(campaignId), "_blank")}>Export CSV</Button>
          </div>
        </SectionCard>

        <SectionCard title="Voucher Codes">
          <div className="space-y-2">
            {detail.vouchers.map((voucher) => (
              <div key={voucher.id} className="rounded-xl border border-border px-3 py-2 text-sm">
                <p className="font-mono font-semibold text-dark">{voucher.code}</p>
                <p className="text-muted">{voucher.status} · {voucher.redeemed_count}/{voucher.max_redemptions}</p>
              </div>
            ))}
          </div>
        </SectionCard>
      </div>
    </PageContainer>
  );
}
