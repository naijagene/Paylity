"use client";

import { useCallback, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { AlertCard } from "@/components/ui/AlertCard";
import { KpiCard, SectionCard } from "@/components/ui/OpsCards";
import {
  buildOpsMarketingCampaignPayload,
  fetchOpsMarketing,
  getOpsMarketingExportCsvUrl,
  opsMarketingCreateCampaign,
  opsMarketingExportUsage,
  opsMarketingSetCampaignActive,
  opsMarketingSetVoucherActive,
  type OpsMarketingCampaign,
} from "@/lib/api/ops";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { usePolling } from "@/lib/hooks/usePolling";

type DistributionMode = "unique_codes" | "shared_code";

const NETWORK_OPTIONS = [
  { value: "", label: "All networks" },
  { value: "MTN", label: "MTN" },
  { value: "Airtel", label: "Airtel" },
  { value: "Glo", label: "Glo" },
  { value: "9mobile", label: "9mobile" },
] as const;

const QUANTITY_OPTIONS = [1, 5, 10, 25, 50, 100];

function distributionLabel(mode: DistributionMode): string {
  return mode === "shared_code" ? "Shared campaign code" : "Unique one-time codes";
}

export function MarketingClient() {
  const [campaignName, setCampaignName] = useState("Soft Launch Airtime");
  const [amount, setAmount] = useState<500 | 1000>(500);
  const [distributionMode, setDistributionMode] = useState<DistributionMode>("unique_codes");
  const [quantity, setQuantity] = useState(5);
  const [maxRedemptions, setMaxRedemptions] = useState(5);
  const [network, setNetwork] = useState("");
  const [expiresAt, setExpiresAt] = useState("");
  const [onePerPhone, setOnePerPhone] = useState(true);
  const [onePerEmail, setOnePerEmail] = useState(true);
  const [onePerDevice, setOnePerDevice] = useState(true);
  const [reservationTimeoutMinutes, setReservationTimeoutMinutes] = useState(30);
  const [active, setActive] = useState(true);
  const [generatedCodes, setGeneratedCodes] = useState<string[]>([]);
  const [sharedMessage, setSharedMessage] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadSnapshot = useCallback(async () => fetchOpsMarketing(), []);
  const snapshot = usePolling({ fetcher: loadSnapshot, intervalMs: 60000 });
  const data = snapshot.data;

  async function handleCreateCampaign() {
    setSubmitting(true);
    setError(null);

    try {
      const payload = buildOpsMarketingCampaignPayload({
        name: campaignName,
        amount,
        distributionMode,
        quantity,
        maxRedemptions,
        network,
        expiresAt,
        active,
        onePerPhone,
        onePerEmail,
        onePerDevice,
        reservationTimeoutMinutes,
      });

      const result = await opsMarketingCreateCampaign(payload);
      setGeneratedCodes(result.codes);
      setSharedMessage(result.shared_message ?? null);
      await snapshot.refresh();
    } catch (campaignError) {
      if (campaignError instanceof ApiOfflineError) {
        setError("Network unavailable. Check the API server and try again.");
      } else if (campaignError instanceof ApiError) {
        setError(campaignError.message);
      } else {
        setError("Unable to create campaign.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function copyText(value: string) {
    await navigator.clipboard.writeText(value);
  }

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-3xl font-extrabold text-dark">Launch Vouchers</h1>
            <p className="mt-2 text-sm text-muted">
              Generate secure one-time codes or shareable campaign codes with anti-abuse controls.
            </p>
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
            <label className="block text-sm md:col-span-2">
              <span className="font-semibold text-dark">Distribution mode</span>
              <div className="mt-2 flex flex-wrap gap-4">
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="distribution_mode"
                    checked={distributionMode === "unique_codes"}
                    onChange={() => setDistributionMode("unique_codes")}
                  />
                  <span>Unique one-time codes</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="radio"
                    name="distribution_mode"
                    checked={distributionMode === "shared_code"}
                    onChange={() => setDistributionMode("shared_code")}
                  />
                  <span>Shared campaign code</span>
                </label>
              </div>
            </label>
            {distributionMode === "unique_codes" ? (
              <label className="block text-sm">
                <span className="font-semibold text-dark">Number of codes</span>
                <select
                  className="mt-2 w-full rounded-xl border border-border px-3 py-2"
                  value={quantity}
                  onChange={(event) => setQuantity(Number(event.target.value))}
                >
                  {QUANTITY_OPTIONS.map((value) => (
                    <option key={value} value={value}>
                      {value}
                    </option>
                  ))}
                </select>
              </label>
            ) : (
              <label className="block text-sm">
                <span className="font-semibold text-dark">Maximum successful redemptions</span>
                <input
                  type="number"
                  min={1}
                  max={10000}
                  className="mt-2 w-full rounded-xl border border-border px-3 py-2"
                  value={maxRedemptions}
                  onChange={(event) => setMaxRedemptions(Number(event.target.value))}
                />
              </label>
            )}
            <label className="block text-sm">
              <span className="font-semibold text-dark">Network</span>
              <select
                className="mt-2 w-full rounded-xl border border-border px-3 py-2"
                value={network}
                onChange={(event) => setNetwork(event.target.value)}
              >
                {NETWORK_OPTIONS.map((option) => (
                  <option key={option.label} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="font-semibold text-dark">Expiry date/time</span>
              <input
                type="datetime-local"
                className="mt-2 w-full rounded-xl border border-border px-3 py-2"
                value={expiresAt}
                onChange={(event) => setExpiresAt(event.target.value)}
              />
            </label>
            <label className="block text-sm">
              <span className="font-semibold text-dark">Reservation timeout (minutes)</span>
              <input
                type="number"
                min={5}
                max={1440}
                className="mt-2 w-full rounded-xl border border-border px-3 py-2"
                value={reservationTimeoutMinutes}
                onChange={(event) => setReservationTimeoutMinutes(Number(event.target.value))}
              />
            </label>
            <div className="space-y-2 text-sm md:col-span-2">
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={onePerPhone} onChange={(event) => setOnePerPhone(event.target.checked)} />
                <span>One redemption per phone</span>
              </label>
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={onePerEmail} onChange={(event) => setOnePerEmail(event.target.checked)} />
                <span>One redemption per email</span>
              </label>
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={onePerDevice} onChange={(event) => setOnePerDevice(event.target.checked)} />
                <span>One redemption per device</span>
              </label>
              <label className="flex items-center gap-2">
                <input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} />
                <span>Active</span>
              </label>
            </div>
          </div>
          {distributionMode === "shared_code" ? (
            <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
              This code may be forwarded publicly. Campaign-level phone, device, email, quantity, and expiry controls
              will still apply.
            </p>
          ) : null}
          {error ? (
            <AlertCard
              severity="critical"
              title="Unable to create campaign"
              message={<p className="whitespace-pre-line">{error}</p>}
            />
          ) : null}
          <Button type="button" className="mt-4" onClick={() => void handleCreateCampaign()} disabled={submitting}>
            {submitting ? "Generating..." : distributionMode === "shared_code" ? "Generate Shared Code" : "Generate Codes"}
          </Button>
          {generatedCodes.length > 0 ? (
            <div className="mt-4 rounded-xl border border-border bg-muted/20 p-4">
              <p className="text-sm font-semibold text-dark">
                {distributionMode === "shared_code" ? "Shared campaign code" : "Newly generated codes"}
              </p>
              <div className="mt-3 space-y-2 font-mono text-sm">
                {generatedCodes.map((code) => (
                  <div key={code} className="flex items-center justify-between gap-3">
                    <span>{code}</span>
                    <Button type="button" variant="outline" onClick={() => void copyText(code)}>
                      Copy code
                    </Button>
                  </div>
                ))}
              </div>
              {sharedMessage ? (
                <div className="mt-4 rounded-xl border border-border bg-white p-3 text-sm text-muted">
                  <p className="font-semibold text-dark">Campaign message</p>
                  <p className="mt-2">{sharedMessage}</p>
                  <Button type="button" variant="outline" className="mt-3" onClick={() => void copyText(sharedMessage)}>
                    Copy campaign message
                  </Button>
                </div>
              ) : null}
              {distributionMode === "unique_codes" ? (
                <Button
                  type="button"
                  variant="outline"
                  className="mt-3"
                  onClick={() => void copyText(generatedCodes.join("\n"))}
                >
                  Copy all
                </Button>
              ) : null}
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
                  <CampaignRow
                    key={campaign.id}
                    campaign={campaign}
                    onToggleActive={(nextActive) =>
                      void opsMarketingSetCampaignActive(campaign.id, nextActive).then(() => snapshot.refresh())
                    }
                  />
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

function CampaignRow({
  campaign,
  onToggleActive,
}: {
  campaign: OpsMarketingCampaign;
  onToggleActive: (active: boolean) => void;
}) {
  const sharedCode = campaign.shared_code_value ?? null;
  const sharedMessage = campaign.shared_message ?? null;

  return (
    <div className="rounded-xl border border-border px-4 py-3">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="space-y-2">
          <p className="font-semibold text-dark">{campaign.name}</p>
          <p className="text-sm text-muted">
            ₦{campaign.amount.toLocaleString("en-NG")} · {distributionLabel(campaign.distribution_mode)} ·{" "}
            {campaign.generated_count} code{campaign.generated_count === 1 ? "" : "s"}
            {campaign.distribution_mode === "shared_code" && campaign.max_redemptions
              ? ` · max ${campaign.max_redemptions}`
              : ""}
          </p>
          <div className="grid gap-1 text-sm text-muted sm:grid-cols-2">
            <span>Unused/available: {campaign.unused_count ?? 0}</span>
            <span>Reserved: {campaign.reserved_count ?? 0}</span>
            <span>Redeemed: {campaign.redeemed_count}</span>
            <span>Released: {campaign.released_count ?? 0}</span>
            <span>Expired reservations: {campaign.expired_reservations ?? 0}</span>
            <span>Remaining capacity: {campaign.remaining_capacity ?? 0}</span>
            <span>One per phone: {campaign.one_per_phone ? "Yes" : "No"}</span>
            <span>One per device: {campaign.one_per_device ? "Yes" : "No"}</span>
            <span>One per email: {campaign.one_per_email ? "Yes" : "No"}</span>
            <span>Expiry: {campaign.expires_at ? new Date(campaign.expires_at).toLocaleString() : "None"}</span>
            <span>Status: {campaign.active ? "Active" : "Inactive"}</span>
          </div>
          {sharedCode ? (
            <div className="flex flex-wrap gap-2 pt-2">
              <Button type="button" variant="outline" onClick={() => void navigator.clipboard.writeText(sharedCode)}>
                Copy shared code
              </Button>
              {sharedMessage ? (
                <Button type="button" variant="outline" onClick={() => void navigator.clipboard.writeText(sharedMessage)}>
                  Copy campaign message
                </Button>
              ) : null}
              <Button type="button" variant="outline" onClick={() => void opsMarketingExportUsage(campaign.id)}>
                Export history (JSON)
              </Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  window.open(getOpsMarketingExportCsvUrl(campaign.id), "_blank");
                }}
              >
                Export CSV
              </Button>
            </div>
          ) : null}
        </div>
        <Button type="button" variant="outline" onClick={() => onToggleActive(!campaign.active)}>
          {campaign.active ? "Deactivate immediately" : "Activate"}
        </Button>
      </div>
    </div>
  );
}
