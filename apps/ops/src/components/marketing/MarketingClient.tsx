"use client";

import { useCallback, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import {
  fetchOpsMarketing,
  opsMarketingCreateCampaign,
  opsMarketingExportUsage,
  opsMarketingSetCampaignActive,
  opsMarketingSetVoucherActive,
} from "@/lib/api/ops";
import { usePolling } from "@/lib/hooks/usePolling";

export function MarketingClient() {
  const [campaignName, setCampaignName] = useState("Soft Launch Airtime");
  const [amount, setAmount] = useState<500 | 1000>(500);
  const [quantity, setQuantity] = useState(5);
  const [generatedCodes, setGeneratedCodes] = useState<string[]>([]);
  const [sharedCode, setSharedCode] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadSnapshot = useCallback(async () => fetchOpsMarketing(), []);
  const snapshot = usePolling({ fetcher: loadSnapshot, intervalMs: 60000 });
  const data = snapshot.data;

  async function handleCreateCampaign() {
    setSubmitting(true);
    setError(null);

    try {
      const result = await opsMarketingCreateCampaign({
        name: campaignName,
        amount,
        quantity,
        one_per_phone: true,
        one_per_device: true,
        shared_code: sharedCode,
      });
      setGeneratedCodes(result.codes);
      await snapshot.refresh();
    } catch (campaignError) {
      setError(campaignError instanceof Error ? campaignError.message : "Unable to create campaign.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-3xl font-extrabold text-dark">Launch Vouchers</h1>
            <p className="mt-2 text-sm text-muted">Generate secure one-time airtime voucher campaigns.</p>
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

        <SectionCard title="Create Voucher Campaign">
          <div className="grid gap-4 md:grid-cols-2">
            <label className="block text-sm">
              <span className="font-semibold text-dark">Campaign name</span>
              <input
                className="mt-2 w-full rounded-xl border border-border px-3 py-2"
                value={campaignName}
                onChange={(event) => setCampaignName(event.target.value)}
              />
            </label>
            <label className="block text-sm">
              <span className="font-semibold text-dark">Airtime value</span>
              <select
                className="mt-2 w-full rounded-xl border border-border px-3 py-2"
                value={amount}
                onChange={(event) => setAmount(Number(event.target.value) as 500 | 1000)}
              >
                <option value={500}>₦500</option>
                <option value={1000}>₦1,000</option>
              </select>
            </label>
            <label className="block text-sm">
              <span className="font-semibold text-dark">Number of unique codes</span>
              <select
                className="mt-2 w-full rounded-xl border border-border px-3 py-2"
                value={quantity}
                onChange={(event) => setQuantity(Number(event.target.value))}
              >
                {[1, 5, 10, 25, 50, 100].map((value) => (
                  <option key={value} value={value}>
                    {value}
                  </option>
                ))}
              </select>
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={sharedCode}
                onChange={(event) => setSharedCode(event.target.checked)}
              />
              <span>Shared campaign code (not recommended)</span>
            </label>
          </div>
          {sharedCode ? (
            <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
              Shared codes can be forwarded and abused. Unique codes are recommended.
            </p>
          ) : null}
          {error ? <p className="mt-3 text-sm text-error">{error}</p> : null}
          <Button type="button" className="mt-4" onClick={() => void handleCreateCampaign()} disabled={submitting}>
            {submitting ? "Generating..." : "Generate Codes"}
          </Button>
          {generatedCodes.length > 0 ? (
            <div className="mt-4 rounded-xl border border-border bg-muted/20 p-4">
              <p className="text-sm font-semibold text-dark">Newly generated codes</p>
              <div className="mt-3 space-y-2 font-mono text-sm">
                {generatedCodes.map((code) => (
                  <div key={code} className="flex items-center justify-between gap-3">
                    <span>{code}</span>
                    <Button type="button" variant="outline" onClick={() => void navigator.clipboard.writeText(code)}>
                      Copy
                    </Button>
                  </div>
                ))}
              </div>
              <Button
                type="button"
                variant="outline"
                className="mt-3"
                onClick={() => void navigator.clipboard.writeText(generatedCodes.join("\n"))}
              >
                Copy all
              </Button>
            </div>
          ) : null}
        </SectionCard>

        {data ? (
          <>
            <SectionCard title="Marketing KPIs">
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Generated" value={String(data.kpis.generated)} />
                <KpiCard label="Unused" value={String(data.kpis.unused ?? 0)} />
                <KpiCard label="Reserved" value={String(data.kpis.reserved ?? 0)} />
                <KpiCard label="Redeemed" value={String(data.kpis.redeemed)} />
                <KpiCard label="Blocked" value={String(data.kpis.blocked_attempts ?? 0)} />
                <KpiCard label="Review Rate" value={`${data.kpis.review_rate_pct}%`} />
                <KpiCard label="Share Rate" value={`${data.kpis.share_rate_pct}%`} />
                <KpiCard label="Avg Rating" value={data.reviews.average_rating?.toFixed(1) ?? "—"} />
              </div>
            </SectionCard>

            <SectionCard title="Campaigns">
              <div className="space-y-3">
                {(data.campaigns ?? []).map((campaign) => (
                  <div key={campaign.id} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold text-dark">{campaign.name}</p>
                        <p className="text-sm text-muted">
                          ₦{campaign.amount.toLocaleString("en-NG")} · {campaign.generated_count} generated ·{" "}
                          {campaign.redeemed_count} redeemed · {campaign.unused_count ?? 0} unused
                        </p>
                      </div>
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                          void opsMarketingSetCampaignActive(campaign.id, !campaign.active).then(() =>
                            snapshot.refresh(),
                          )
                        }
                      >
                        {campaign.active ? "Deactivate" : "Activate"}
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </SectionCard>

            <SectionCard title="Voucher Codes">
              <div className="space-y-3">
                {data.vouchers.map((voucher) => (
                  <div key={voucher.id} className="rounded-xl border border-border px-4 py-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                      <div>
                        <p className="font-semibold text-dark">{voucher.name}</p>
                        <p className="text-sm text-muted">
                          {voucher.code} · ₦{voucher.amount.toLocaleString("en-NG")} · {voucher.status} ·{" "}
                          {voucher.redeemed_count}/{voucher.max_redemptions}
                        </p>
                      </div>
                      <Button
                        type="button"
                        variant="outline"
                        disabled={voucher.immutable}
                        onClick={() =>
                          void opsMarketingSetVoucherActive(voucher.id, !voucher.active).then(() =>
                            snapshot.refresh(),
                          )
                        }
                      >
                        {voucher.active ? "Deactivate" : "Enable"}
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
