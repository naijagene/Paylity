"use client";

import { useEffect, useMemo, useState } from "react";
import { AdSlot } from "@/components/ads/AdSlot";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import type { ProductType } from "@/lib/checkout/types";
import {
  PROCESSING_MESSAGE_INTERVAL_MS,
  PROCESSING_STATUS_MESSAGES,
} from "@/lib/ui/processingMessages";

const CUSTOMER_TIPS = [
  "Keep this page open until processing completes.",
  "Your payment is encrypted and securely handled.",
  "Save your receipt for support and verification.",
  "Double-check the recipient details before paying.",
  "Most purchases complete in under 15 seconds.",
] as const;

const PRODUCT_ICONS: Record<ProductType, { emoji: string; label: string }> = {
  airtime: { emoji: "📱", label: "Airtime purchase" },
  data: { emoji: "📶", label: "Data purchase" },
  electricity: { emoji: "⚡", label: "Electricity purchase" },
};

type CheckoutProcessingScreenProps = {
  product: ProductType;
  transactionRef?: string | null;
};

export function CheckoutProcessingScreen({
  product,
  transactionRef,
}: CheckoutProcessingScreenProps) {
  const [messageIndex, setMessageIndex] = useState(0);
  const [tipIndex, setTipIndex] = useState(0);
  const [progress, setProgress] = useState(12);

  const productIcon = PRODUCT_ICONS[product];

  useEffect(() => {
    const messageTimer = window.setInterval(() => {
      setMessageIndex((current) => (current + 1) % PROCESSING_STATUS_MESSAGES.length);
    }, PROCESSING_MESSAGE_INTERVAL_MS);

    const tipTimer = window.setInterval(() => {
      setTipIndex((current) => (current + 1) % CUSTOMER_TIPS.length);
    }, 4200);

    const progressTimer = window.setInterval(() => {
      setProgress((current) => {
        if (current >= 92) {
          return current;
        }

        const step = current < 60 ? 8 : current < 80 ? 4 : 2;
        return Math.min(current + step, 92);
      });
    }, 900);

    return () => {
      window.clearInterval(messageTimer);
      window.clearInterval(tipTimer);
      window.clearInterval(progressTimer);
    };
  }, []);

  const statusMessage = useMemo(
    () => PROCESSING_STATUS_MESSAGES[messageIndex],
    [messageIndex],
  );
  const customerTip = useMemo(() => CUSTOMER_TIPS[tipIndex], [tipIndex]);

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-dark/70 px-4 py-6 backdrop-blur-sm sm:py-10">
      <div className="mx-auto flex min-h-full w-full max-w-lg items-center">
        <div className="animate-fade-in w-full space-y-5 rounded-3xl border border-border-green bg-card p-6 shadow-2xl sm:p-8">
          <PaylityLogo size="sm" href={null} priority />

          <div className="text-center">
            <div
              className="mx-auto mb-4 flex h-20 w-20 animate-product-float items-center justify-center rounded-3xl bg-success-light text-4xl shadow-inner"
              aria-hidden="true"
            >
              {productIcon.emoji}
            </div>
            <p className="text-xs font-semibold uppercase tracking-wide text-success">
              {productIcon.label}
            </p>
            <h2 className="mt-3 font-display text-2xl font-extrabold text-dark">
              We&apos;re processing your request
            </h2>
            <p className="mt-3 text-sm leading-relaxed text-muted">
              Please keep this page open. Your purchase is being securely
              processed.
            </p>
          </div>

          <div
            className="rounded-2xl border border-border bg-background px-4 py-4"
            aria-live="polite"
          >
            <p className="text-sm font-semibold text-dark">{statusMessage}</p>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-dark/10">
              <div
                className="h-full rounded-full bg-success transition-all duration-700 ease-out motion-reduce:transition-none"
                style={{ width: `${progress}%` }}
                role="progressbar"
                aria-valuenow={progress}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label="Processing progress"
              />
            </div>
            <p className="mt-3 text-xs font-medium text-muted">
              Usually takes less than 15 seconds
            </p>
          </div>

          <div className="rounded-2xl border border-border-green bg-success-light/30 px-4 py-3">
            <p className="text-xs font-semibold uppercase tracking-wide text-success">
              Tip
            </p>
            <p className="mt-1 text-sm text-dark">{customerTip}</p>
          </div>

          {transactionRef ? (
            <p className="text-center font-mono text-xs text-muted">
              Ref: {transactionRef}
            </p>
          ) : null}

          <AdSlot type="checkout-banner" />
        </div>
      </div>
    </div>
  );
}
