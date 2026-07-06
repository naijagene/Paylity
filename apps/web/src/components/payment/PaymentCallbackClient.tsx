"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { ErrorStatePage } from "@/components/transaction/ErrorStatePage";
import { PaymentProcessingCard } from "@/components/payment/PaymentProcessingCard";
import { PaymentSuccessCard } from "@/components/transaction/PaymentSuccessCard";
import { PaymentVerificationSkeleton } from "@/components/transaction/TransactionPageSkeleton";
import { TransactionTimeline } from "@/components/transaction/TransactionTimeline";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import { BackHomeLink } from "@/components/transaction/BackHomeLink";
import { SupportCard } from "@/components/support/SupportCard";
import { verifyPaystackPayment } from "@/lib/api/payments";
import { getTransaction } from "@/lib/api/transactions";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import {
  getReceiptPhoneDisplay,
  getReceiptProductLabel,
} from "@/lib/receipt/display";
import {
  getBadgeState,
  getHeroState,
  getTimelineState,
  isTerminalTransactionStatus,
  shouldRedirectToTransactionStatus,
  shouldShowFulfillmentProcessingPage,
  toTransactionLike,
} from "@/lib/transaction/display";
import { DEFAULT_MAX_POLL_ATTEMPTS } from "@/lib/transaction/polling";
import {
  saveTransactionSession,
} from "@/lib/transaction/session";

const POLL_INTERVAL_MS = 5000;

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
      productLabel: string;
      productAmount: number;
      convenienceFee: number;
      gatewayFee: number;
      payableAmount: number;
      customerPhone: string;
      customerEmail?: string | null;
      timestamp?: string | null;
      timestampDisplay?: string | null;
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
    productLabel: getReceiptProductLabel(result.receipt, result.product_type),
    productAmount: result.product_amount,
    convenienceFee: result.convenience_fee,
    gatewayFee: result.gateway_fee,
    payableAmount: result.payable_amount,
    customerPhone: getReceiptPhoneDisplay(result.receipt),
    customerEmail: result.receipt?.customer_email,
    timestamp: result.receipt?.timestamp ?? result.verified_at,
    timestampDisplay: result.receipt?.timestamp_display,
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
  const pollAttemptsRef = useRef(0);

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

        if (isTerminalTransactionStatus(result.status)) {
          if (shouldRedirectToTransactionStatus(result.status)) {
            router.replace(`/transaction/${encodeURIComponent(reference)}`);
          }
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

  const processingStatus =
    state.kind === "verified" ? state.status : null;

  useEffect(() => {
    if (!reference || !processingStatus) {
      return;
    }

    if (!shouldShowFulfillmentProcessingPage(processingStatus)) {
      return;
    }

    let cancelled = false;
    pollAttemptsRef.current = 0;

    const pollStatus = async () => {
      pollAttemptsRef.current += 1;

      try {
        const transaction = await getTransaction(reference);

        if (cancelled) {
          return;
        }

        saveTransactionSession(transaction.reference, transaction.status);

        if (isTerminalTransactionStatus(transaction.status)) {
          if (shouldRedirectToTransactionStatus(transaction.status)) {
            router.replace(`/transaction/${encodeURIComponent(reference)}`);
          }
          return;
        }

        setState((current) =>
          current.kind === "verified"
            ? { ...current, status: transaction.status }
            : current,
        );
      } catch {
        // Keep showing the processing page during transient polling errors.
      }
    };

    void pollStatus();
    const intervalId = window.setInterval(() => {
      if (pollAttemptsRef.current >= DEFAULT_MAX_POLL_ATTEMPTS) {
        window.clearInterval(intervalId);
        router.replace(`/transaction/${encodeURIComponent(reference)}`);
        return;
      }

      void pollStatus();
    }, POLL_INTERVAL_MS);

    return () => {
      cancelled = true;
      window.clearInterval(intervalId);
    };
  }, [reference, router, processingStatus]);

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

  if (
    state.kind === "verified" &&
    shouldRedirectToTransactionStatus(state.status)
  ) {
    return (
      <PageContainer>
        <PaymentVerificationSkeleton />
      </PageContainer>
    );
  }

  if (shouldShowFulfillmentProcessingPage(state.status)) {
    return (
      <PageContainer className="py-8 sm:py-12">
        <PaymentProcessingCard reference={state.reference} />
      </PageContainer>
    );
  }

  const transaction = toTransactionLike(state.status);
  const hero = getHeroState(transaction);
  const badges = getBadgeState(transaction);
  const timeline = getTimelineState(transaction);

  if (hero.layout === "success_card") {
    return (
      <PageContainer className="py-8 sm:py-12">
        <PaymentSuccessCard
          reference={state.reference}
          productLabel={state.productLabel}
          customerPhone={state.customerPhone}
          customerEmail={state.customerEmail}
          productAmount={state.productAmount}
          convenienceFee={state.convenienceFee}
          gatewayFee={state.gatewayFee}
          payableAmount={state.payableAmount}
          transactionStatus={state.status}
          failureReason={state.failureReason}
          timestamp={state.timestamp}
          timestampDisplay={state.timestampDisplay}
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
