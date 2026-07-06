"use client";

import { useEffect, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { AppFooter } from "@/components/system/AppFooter";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import {
  verifyReceipt,
  type ReceiptVerificationResult,
} from "@/lib/api/verification";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { formatReceiptTimestamp } from "@/lib/receipt/display";
import {
  getFulfillmentBadgeLabel,
  getFulfillmentBadgeVariant,
  getPaymentBadgeLabel,
  getPaymentBadgeVariant,
} from "@/lib/transaction/display";

type PageState =
  | { kind: "loading" }
  | { kind: "offline" }
  | { kind: "error"; message: string }
  | { kind: "verified"; result: ReceiptVerificationResult };

function VerificationShield() {
  return (
    <div
      className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-success text-white shadow-lg shadow-success/20"
      aria-hidden="true"
    >
      <svg viewBox="0 0 24 24" className="h-10 w-10" fill="currentColor">
        <path d="M12 1 3 5v6c0 5.25 3.75 10.15 9 11 5.25-.85 9-5.75 9-11V5l-9-4Zm-1 14-3-3 1.4-1.4L11 12.2l4.6-4.6L17 9l-6 6Z" />
      </svg>
    </div>
  );
}

export function ReceiptVerificationClient({ token }: { token: string }) {
  const [state, setState] = useState<PageState>({ kind: "loading" });

  useEffect(() => {
    let cancelled = false;

    verifyReceipt(token)
      .then((result) => {
        if (!cancelled) {
          setState({ kind: "verified", result });
        }
      })
      .catch((error) => {
        if (cancelled) {
          return;
        }

        if (error instanceof ApiOfflineError) {
          setState({ kind: "offline" });
          return;
        }

        if (error instanceof ApiError) {
          setState({ kind: "error", message: error.message });
          return;
        }

        setState({
          kind: "error",
          message: "Unable to verify this receipt.",
        });
      });

    return () => {
      cancelled = true;
    };
  }, [token]);

  return (
    <PageContainer className="py-8 sm:py-12">
      <div className="animate-fade-in mx-auto w-full max-w-xl space-y-7">
        <header className="border-b border-border pb-5">
          <PaylityLogo size="md" href="/" />
        </header>

        <section className="text-center sm:text-left">
          <p className="text-sm font-semibold uppercase tracking-wide text-success">
            Receipt Verification
          </p>
          <h1 className="mt-2 font-display text-3xl font-extrabold text-dark">
            Verify Authenticity
          </h1>
          <p className="mt-2 text-sm text-muted">
            Confirm a PAYLITY receipt without exposing sensitive customer
            details.
          </p>
        </section>

        {state.kind === "loading" ? (
          <p className="rounded-2xl border border-border bg-card px-4 py-6 text-center text-sm text-muted">
            Verifying receipt…
          </p>
        ) : null}

        {state.kind === "offline" ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            Network unavailable. Check your connection and try again.
          </p>
        ) : null}

        {state.kind === "error" ? (
          <div className="space-y-4 rounded-2xl border border-error/20 bg-error/5 p-5">
            <p className="text-sm text-error">{state.message}</p>
            <Button href="/" variant="outline">
              Back Home
            </Button>
          </div>
        ) : null}

        {state.kind === "verified" ? (
          <section className="space-y-5 rounded-3xl border border-border-green bg-success-light/30 p-6 shadow-sm sm:p-7">
            <div className="text-center">
              <VerificationShield />
              <p className="mt-5 text-lg font-bold text-dark">
                Verified by PAYLITY
              </p>
              {state.result.verified_at ? (
                <p className="mt-2 text-sm text-muted">
                  Verified{" "}
                  {formatReceiptTimestamp(
                    state.result.verified_at,
                    null,
                  ) ?? new Date(state.result.verified_at).toLocaleString("en-NG")}
                </p>
              ) : null}
            </div>
            <dl className="space-y-4 text-sm">
              <div className="flex justify-between gap-4 border-b border-dark/5 pb-4">
                <dt className="text-muted">Transaction Reference</dt>
                <dd className="font-mono font-semibold text-dark">
                  {state.result.reference}
                </dd>
              </div>
              <div className="flex justify-between gap-4 border-b border-dark/5 pb-4">
                <dt className="text-muted">Product</dt>
                <dd className="font-semibold text-dark">
                  {state.result.product_label}
                </dd>
              </div>
              <div className="flex justify-between gap-4 border-b border-dark/5 pb-4">
                <dt className="text-muted">Phone</dt>
                <dd className="font-semibold text-dark">
                  {state.result.customer_phone_masked}
                </dd>
              </div>
              <div className="flex justify-between gap-4 border-b border-dark/5 pb-4">
                <dt className="text-muted">Amount</dt>
                <dd className="font-semibold text-dark">
                  {formatNaira(state.result.payable_amount)}
                </dd>
              </div>
              {state.result.timestamp ? (
                <div className="flex justify-between gap-4">
                  <dt className="text-muted">Transaction Time</dt>
                  <dd className="font-semibold text-dark">
                    {formatReceiptTimestamp(state.result.timestamp, null) ??
                      new Date(state.result.timestamp).toLocaleString("en-NG")}
                  </dd>
                </div>
              ) : null}
            </dl>
            <div className="flex flex-wrap gap-2">
              <StatusBadge
                label={getPaymentBadgeLabel(state.result.status)}
                variant={getPaymentBadgeVariant(state.result.status)}
              />
              <StatusBadge
                label={getFulfillmentBadgeLabel(state.result.status)}
                variant={getFulfillmentBadgeVariant(state.result.status)}
              />
            </div>
          </section>
        ) : null}
      </div>

      <AppFooter className="mt-8" />
    </PageContainer>
  );
}
