"use client";

import { useState } from "react";
import { Button } from "@/components/Button";
import {
  extractElectricityTokenDetails,
  getPrimaryElectricityToken,
  type ElectricityTokenDetails,
} from "@/lib/transaction/electricity";

type ElectricityTokenCardProps = {
  reference: string;
  fulfillmentDetails?: ElectricityTokenDetails | null;
  responsePayload?: Record<string, unknown> | null;
  className?: string;
};

const DETAIL_LABELS: Array<{
  key: keyof ElectricityTokenDetails;
  label: string;
}> = [
  { key: "token", label: "Token" },
  { key: "purchased_code", label: "Purchased Code" },
  { key: "units", label: "Units" },
  { key: "tariff", label: "Tariff" },
  { key: "resetToken", label: "Reset Token" },
  { key: "configureToken", label: "Configure Token" },
  { key: "tokenAmount", label: "Token Amount" },
  { key: "costOfUnit", label: "Cost of Unit" },
  { key: "tariffBaseRate", label: "Tariff Base Rate" },
];

export function ElectricityTokenCard({
  reference,
  fulfillmentDetails,
  responsePayload,
  className = "",
}: ElectricityTokenCardProps) {
  const [copied, setCopied] = useState(false);

  const details =
    fulfillmentDetails ??
    extractElectricityTokenDetails(
      (responsePayload?.fulfillment as Record<string, unknown> | undefined) ??
        null,
    );

  const primaryToken = getPrimaryElectricityToken(details);

  const handleCopyToken = async () => {
    if (!primaryToken) {
      return;
    }

    try {
      await navigator.clipboard.writeText(primaryToken);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      setCopied(false);
    }
  };

  const handlePrint = () => {
    window.print();
  };

  if (!details) {
    return (
      <section
        className={`rounded-3xl border border-dark/5 bg-white p-5 shadow-sm ${className}`}
        aria-label="Electricity token details"
      >
        <h2 className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
          Electricity Token
        </h2>
        <p className="mt-3 text-sm text-foreground/70">
          Your electricity purchase was delivered, but token details are not
          available in this receipt. Contact support with reference{" "}
          <span className="font-mono font-semibold">{reference}</span> if you
          need your token.
        </p>
      </section>
    );
  }

  return (
    <section
      className={`rounded-3xl border border-success/20 bg-gradient-to-br from-success/10 via-white to-success/5 p-5 shadow-sm ${className}`}
      aria-label="Electricity token details"
    >
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Electricity Token
          </h2>
          <p className="mt-2 text-sm text-foreground/70">
            Save or copy your token below. You can also print this receipt.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:flex-row">
          <Button
            type="button"
            variant="outline"
            className="w-full sm:w-auto"
            onClick={() => void handleCopyToken()}
            disabled={!primaryToken}
          >
            {copied ? "Copied" : "Copy Token"}
          </Button>
          <Button
            type="button"
            className="w-full sm:w-auto"
            onClick={handlePrint}
          >
            Print Receipt
          </Button>
        </div>
      </div>

      <dl className="mt-5 grid gap-3 sm:grid-cols-2">
        {DETAIL_LABELS.map(({ key, label }) => {
          const value = details[key];

          if (!value) {
            return null;
          }

          return (
            <div
              key={key}
              className="rounded-2xl border border-dark/5 bg-white/80 px-4 py-3"
            >
              <dt className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
                {label}
              </dt>
              <dd className="mt-1 break-all font-mono text-sm font-semibold text-dark">
                {String(value)}
              </dd>
            </div>
          );
        })}
      </dl>
    </section>
  );
}
