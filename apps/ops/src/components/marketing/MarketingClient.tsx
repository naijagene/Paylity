"use client";

import { useCallback } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import {
  fetchOpsMarketing,
  opsMarketingCreateVoucher,
  opsMarketingExportUsage,
  opsMarketingSetVoucherActive,
} from "@/lib/api/ops";
import { usePolling } from "@/lib/hooks/usePolling";

export function MarketingClient() {
  const loadSnapshot = useCallback(async () => fetchOpsMarketing(), []);
  const snapshot = usePolling({ fetcher: loadSnapshot, intervalMs: 60000 });
  const data = snapshot.data;

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-3xl font-extrabold text-dark">Launch Vouchers</h1>
            <p className="mt-2 text-sm text-muted">Marketing controls for soft launch airtime vouchers.</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="outline" onClick={() => void snapshot.refresh()}>
              Refresh
            </Button>
            <Button type="button" variant="outline" onClick={() => void opsMarketingExportUsage()}>
              Export Usage
            </Button>
          </div>
        </header>

        {data ? (
          <>
            <SectionCard title="Marketing KPIs">
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Generated" value={String(data.kpis.generated)} />
                <KpiCard label="Redeemed" value={String(data.kpis.redeemed)} />
                <KpiCard label="Remaining" value={String(data.kpis.remaining)} />
                <KpiCard label="Active" value={String(data.kpis.active)} />
                <KpiCard label="Expired" value={String(data.kpis.expired)} />
                <KpiCard label="Review Rate" value={`${data.kpis.review_rate_pct}%`} />
                <KpiCard label="Share Rate" value={`${data.kpis.share_rate_pct}%`} />
                <KpiCard label="Avg Rating" value={data.reviews.average_rating?.toFixed(1) ?? "—"} />
              </div>
            </SectionCard>

            <SectionCard title="Vouchers">
              <div className="space-y-3">
                {data.vouchers.map((voucher) => (
                  <div key={voucher.id} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold text-dark">{voucher.name}</p>
                        <p className="text-sm text-muted">
                          {voucher.code} · ₦{voucher.amount.toLocaleString("en-NG")} · {voucher.redeemed_count}/
                          {voucher.max_redemptions} redeemed
                        </p>
                      </div>
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                          void opsMarketingSetVoucherActive(voucher.id, !voucher.active).then(() =>
                            snapshot.refresh(),
                          )
                        }
                      >
                        {voucher.active ? "Disable" : "Enable"}
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </SectionCard>
          </>
        ) : null}
      </div>
    </PageContainer>
  );
}
