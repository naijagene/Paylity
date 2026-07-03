"use client";

import { useCallback, useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { ErrorStatePage } from "@/components/transaction/ErrorStatePage";
import { PaymentSuccessCard } from "@/components/transaction/PaymentSuccessCard";
import { PaymentVerificationSkeleton } from "@/components/transaction/TransactionPageSkeleton";
import { TransactionTimeline } from "@/components/transaction/TransactionTimeline";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import { BackHomeLink } from "@/components/transaction/BackHomeLink";
import { SupportCard } from "@/components/support/SupportCard";
import { verifyPaystackPayment } from "@/lib/api/payments";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import {
  getBadgeState,
  getHeroState,
  getTimelineState,
  isTerminalTransactionStatus,
  PRODUCT_LABELS,
  toTransactionLike,
} from "@/lib/transaction/display";
import {
  saveTransactionSession,
} from "@/lib/transaction/session";

type VerificationState =
  | { kind: "loading" }
  | { kind: "missing_reference" }
  | { kind: "offline" }
  | { kind: "error"; message: string }
  | {
      kind: "verified";
      reference: string;
      status: string;
      paymentStatus: string;
      productType: string;
      productAmount: number;
      convenienceFee: number;
      gatewayFee: number;
      payableAmount: number;
      customerPhone?: string;
      failureReason?: string;
    };

function mapVerificationResult(
  result: Awaited<ReturnType<typeof verifyPaystackPayment>>,
): VerificationState {
  return {
    kind: "verified",
    reference: result.reference,
    status: result.status,
    paymentStatus: result.payment_status,
    productType: result.product_type,
    productAmount: result.product_amount,
    convenienceFee: result.convenience_fee,
    gatewayFee: result.gateway_fee,
    payableAmount: result.payable_amount,
    failureReason: result.failure_reason,
  };
}

function mapVerificationError(error: unknown): VerificationState {
  if (error instanceof ApiOfflineError) {
    return { kind: "offline" };
  }

  if (error instanceof ApiError) {
    return {
      kind: "error",
      message: error.message,
    };
  }

  return {
    kind: "error",
    message: "Something went wrong while confirming your payment.",
  };
}

export function PaymentCallbackClient() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const reference =
    searchParams.get("reference") ?? searchParams.get("trxref");
  const [state, setState] = useState<VerificationState>(() =>
    reference ? { kind: "loading" } : { kind: "missing_reference" },
  );

  const checkAgain = useCallback(async () => {
    if (!reference) {
      setState({ kind: "missing_reference" });
      return;
    }

    setState({ kind: "loading" });

    try {
      const result = await verifyPaystackPayment(reference);
      setState(mapVerificationResult(result));
    } catch (error) {
      setState(mapVerificationError(error));
    }
  }, [reference]);

  useEffect(() => {
    if (!reference) {
      return;
    }

    let cancelled = false;

    verifyPaystackPayment(reference)
      .then((result) => {
        if (cancelled) {
          return;
        }

        const mapped = mapVerificationResult(result);
        setState(mapped);

        saveTransactionSession(result.reference, result.status);

        if (!isTerminalTransactionStatus(result.status)) {
          router.replace(`/transaction/${encodeURIComponent(reference)}`);
        }
      })
      .catch((error) => {
        if (!cancelled) {
          setState(mapVerificationError(error));
        }
      });

    return () => {
      cancelled = true;
    };
  }, [reference, router]);

  if (state.kind === "missing_reference") {
    return (
      <PageContainer>
        <ErrorStatePage
          title="Payment reference missing"
          message="We could not find a payment reference in the callback URL. Please return to checkout and try again."
          icon="warning"
          onPrimaryClick={() => {
            window.location.href = "/checkout?product=airtime";
          }}
          primaryLabel="Try Again"
        />
      </PageContainer>
    );
  }

  if (state.kind === "loading") {
    return (
      <PageContainer>
        <PaymentVerificationSkeleton />
      </PageContainer>
    );
  }

  if (state.kind === "offline") {
    return (
      <PageContainer>
        <ErrorStatePage
          title="Network unavailable"
          message="PAYLITY could not reach the server to verify your payment. Check your connection and try again."
          icon="offline"
          onPrimaryClick={() => void checkAgain()}
          primaryLabel="Retry"
        />
      </PageContainer>
    );
  }

  if (state.kind === "error") {
    return (
      <PageContainer>
        <ErrorStatePage
          title="Payment could not be verified"
          message={state.message}
          icon="error"
          onPrimaryClick={() => void checkAgain()}
          primaryLabel="Retry"
        />
      </PageContainer>
    );
  }

  const productLabel =
    PRODUCT_LABELS[state.productType] ?? state.productType;
  const transaction = toTransactionLike(state.status);
  const hero = getHeroState(transaction);
  const badges = getBadgeState(transaction);
  const timeline = getTimelineState(transaction);

  if (hero.layout === "success_card") {
    return (
      <PageContainer className="py-8 sm:py-12">
        <PaymentSuccessCard
          reference={state.reference}
          productLabel={productLabel}
          customerPhone={state.customerPhone ?? "—"}
          productAmount={state.productAmount}
          convenienceFee={state.convenienceFee}
          gatewayFee={state.gatewayFee}
          payableAmount={state.payableAmount}
          transactionStatus={state.status}
          failureReason={state.failureReason}
        />
      </PageContainer>
    );
  }

  if (hero.layout === "failed_payment") {
    return (
      <PageContainer className="py-8 sm:py-12">
        <div className="animate-fade-in mx-auto w-full max-w-lg space-y-6">
          <section className="rounded-2xl border border-error/15 bg-error/5 p-6 text-center shadow-sm">
            <div
              className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-error text-white"
              aria-hidden="true"
            >
              ✕
            </div>
            <h1 className="text-2xl font-black text-foreground">{hero.title}</h1>
            <p className="mt-3 text-sm text-foreground/60">
              {state.failureReason ?? state.paymentStatus ?? hero.subtitle}
            </p>
            <p className="mt-4 font-mono text-xs text-foreground/50">
              {state.reference}
            </p>
            <div className="mt-4 flex flex-wrap justify-center gap-2">
              <StatusBadge
                label={badges.payment.label}
                variant={badges.payment.variant}
              />
              <StatusBadge
                label={badges.fulfillment.label}
                variant={badges.fulfillment.variant}
              />
            </div>
          </section>

          <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <TransactionTimeline phase={timeline.phase} />
          </section>

          <div className="flex flex-col gap-3 sm:flex-row">
            <Button href="/checkout?product=airtime" className="flex-1">
              Try Again
            </Button>
            <BackHomeLink variant="outline" className="flex-1">
              Back Home
            </BackHomeLink>
          </div>

          <SupportCard reference={state.reference} />
        </div>
      </PageContainer>
    );
  }

  return (
    <PageContainer className="py-8 sm:py-12">
      <div className="animate-fade-in mx-auto w-full max-w-lg space-y-6 text-center">
        {hero.showSpinner ? (
          <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-success-light">
            <div
              className="h-8 w-8 animate-spin rounded-full border-4 border-success/20 border-t-success"
              aria-hidden="true"
            />
          </div>
        ) : null}
        <h1 className="text-2xl font-black text-foreground">{hero.title}</h1>
        <p className="text-sm text-foreground/60">{hero.subtitle}</p>
        <p className="font-mono text-xs text-foreground/50">{state.reference}</p>
        <div className="flex flex-wrap justify-center gap-2">
          <StatusBadge
            label={badges.payment.label}
            variant={badges.payment.variant}
          />
          <StatusBadge
            label={badges.fulfillment.label}
            variant={badges.fulfillment.variant}
          />
        </div>
        <section className="rounded-2xl border border-border bg-card p-5 text-left shadow-sm">
          <TransactionTimeline phase={timeline.phase} />
        </section>
        <Button onClick={() => void checkAgain()}>Check Again</Button>
      </div>
    </PageContainer>
  );
}
