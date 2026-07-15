"use client";

import { useState } from "react";
import { Button } from "@/components/Button";
import { AlertCard } from "@/components/ui/AlertCard";
import { SectionCard } from "@/components/ui/OpsCards";
import {
  buildOpsMarketingCampaignPayload,
  opsMarketingCreateCampaign,
} from "@/lib/api/ops";
import { ApiError, ApiOfflineError } from "@/lib/api/client";

type DistributionMode = "unique_codes" | "shared_code";

const NETWORK_OPTIONS = [
  { value: "", label: "All networks" },
  { value: "MTN", label: "MTN" },
  { value: "Airtel", label: "Airtel" },
  { value: "Glo", label: "Glo" },
  { value: "9mobile", label: "9mobile" },
] as const;

const QUANTITY_OPTIONS = [1, 5, 10, 25, 50, 100];

export function CreateCampaignForm({ onCreated }: { onCreated: () => void }) {
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
      onCreated();
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

  return (
    <SectionCard title="Create Voucher Campaign">
      <div className="grid gap-4 md:grid-cols-2">
        <label className="block text-sm">
          <span className="font-semibold text-dark">Campaign name</span>
          <input className="mt-2 w-full rounded-xl border border-border px-3 py-2" value={campaignName} onChange={(e) => setCampaignName(e.target.value)} />
        </label>
        <label className="block text-sm">
          <span className="font-semibold text-dark">Airtime value</span>
          <select className="mt-2 w-full rounded-xl border border-border px-3 py-2" value={amount} onChange={(e) => setAmount(Number(e.target.value) as 500 | 1000)}>
            <option value={500}>₦500</option>
            <option value={1000}>₦1,000</option>
          </select>
        </label>
        <label className="block text-sm md:col-span-2">
          <span className="font-semibold text-dark">Distribution mode</span>
          <div className="mt-2 flex flex-wrap gap-4">
            <label className="flex items-center gap-2">
              <input type="radio" checked={distributionMode === "unique_codes"} onChange={() => setDistributionMode("unique_codes")} />
              <span>Unique one-time codes</span>
            </label>
            <label className="flex items-center gap-2">
              <input type="radio" checked={distributionMode === "shared_code"} onChange={() => setDistributionMode("shared_code")} />
              <span>Shared campaign code</span>
            </label>
          </div>
        </label>
        {distributionMode === "unique_codes" ? (
          <label className="block text-sm">
            <span className="font-semibold text-dark">Number of codes</span>
            <select className="mt-2 w-full rounded-xl border border-border px-3 py-2" value={quantity} onChange={(e) => setQuantity(Number(e.target.value))}>
              {QUANTITY_OPTIONS.map((value) => (
                <option key={value} value={value}>{value}</option>
              ))}
            </select>
          </label>
        ) : (
          <label className="block text-sm">
            <span className="font-semibold text-dark">Maximum successful redemptions</span>
            <input type="number" min={1} max={10000} className="mt-2 w-full rounded-xl border border-border px-3 py-2" value={maxRedemptions} onChange={(e) => setMaxRedemptions(Number(e.target.value))} />
          </label>
        )}
        <label className="block text-sm">
          <span className="font-semibold text-dark">Network</span>
          <select className="mt-2 w-full rounded-xl border border-border px-3 py-2" value={network} onChange={(e) => setNetwork(e.target.value)}>
            {NETWORK_OPTIONS.map((option) => (
              <option key={option.label} value={option.value}>{option.label}</option>
            ))}
          </select>
        </label>
        <label className="block text-sm">
          <span className="font-semibold text-dark">Expiry date/time</span>
          <input type="datetime-local" className="mt-2 w-full rounded-xl border border-border px-3 py-2" value={expiresAt} onChange={(e) => setExpiresAt(e.target.value)} />
        </label>
        <label className="block text-sm">
          <span className="font-semibold text-dark">Reservation timeout (minutes)</span>
          <input type="number" min={5} max={1440} className="mt-2 w-full rounded-xl border border-border px-3 py-2" value={reservationTimeoutMinutes} onChange={(e) => setReservationTimeoutMinutes(Number(e.target.value))} />
        </label>
        <div className="space-y-2 text-sm md:col-span-2">
          <label className="flex items-center gap-2"><input type="checkbox" checked={onePerPhone} onChange={(e) => setOnePerPhone(e.target.checked)} /><span>One redemption per phone</span></label>
          <label className="flex items-center gap-2"><input type="checkbox" checked={onePerEmail} onChange={(e) => setOnePerEmail(e.target.checked)} /><span>One redemption per email</span></label>
          <label className="flex items-center gap-2"><input type="checkbox" checked={onePerDevice} onChange={(e) => setOnePerDevice(e.target.checked)} /><span>One redemption per device</span></label>
          <label className="flex items-center gap-2"><input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} /><span>Active</span></label>
        </div>
      </div>
      {distributionMode === "shared_code" ? (
        <p className="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          This code may be forwarded publicly. Campaign-level phone, device, email, quantity, and expiry controls will still apply.
        </p>
      ) : null}
      {error ? <AlertCard severity="critical" title="Unable to create campaign" message={<p className="whitespace-pre-line">{error}</p>} /> : null}
      <Button type="button" className="mt-4" onClick={() => void handleCreateCampaign()} disabled={submitting}>
        {submitting ? "Generating..." : distributionMode === "shared_code" ? "Generate Shared Code" : "Generate Codes"}
      </Button>
      {generatedCodes.length > 0 ? (
        <div className="mt-4 rounded-xl border border-border bg-muted/20 p-4 font-mono text-sm">
          {generatedCodes.map((code) => <div key={code}>{code}</div>)}
          {sharedMessage ? <p className="mt-3 text-muted">{sharedMessage}</p> : null}
        </div>
      ) : null}
    </SectionCard>
  );
}
