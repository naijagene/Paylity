"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useParams } from "next/navigation";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { AdSlot } from "@/components/ads/AdSlot";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { ElectricityTokenCard } from "@/components/transaction/ElectricityTokenCard";
import { ErrorStatePage } from "@/components/transaction/ErrorStatePage";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import { TransactionPageSkeleton } from "@/components/transaction/TransactionPageSkeleton";
import { TransactionReceiptCard } from "@/components/transaction/TransactionReceiptCard";
import { TransactionTimeline } from "@/components/transaction/TransactionTimeline";
import { AppFooter } from "@/components/system/AppFooter";
import { SupportCard } from "@/components/support/SupportCard";
import { getTransaction, type TransactionDetail } from "@/lib/api/transactions";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import {
  getFulfillmentBadgeLabel,
  getFulfillmentBadgeVariant,
  getPaymentBadgeLabel,
  getPaymentBadgeVariant,
  getTimelinePhase,
  isAwaitingDelivery,
  shouldPollTransactionStatus,
} from "@/lib/transaction/display";
import {
  getReceiptPhoneDisplay,
  getReceiptProductLabel,
} from "@/lib/receipt/display";
import {
  DEFAULT_MAX_POLL_ATTEMPTS,
  hasPollingExhausted,
} from "@/lib/transaction/polling";
import {
  updateTransactionSessionStatus,
} from "@/lib/transaction/session";
import { CopyButton } from "@/components/ui/CopyButton";
import { BackHomeLink } from "@/components/transaction/BackHomeLink";

const REFERENCE_PATTERN = /^PYL-\d{8}-[A-Z0-9]{6}$/;
const MAX_POLL_ATTEMPTS = DEFAULT_MAX_POLL_ATTEMPTS;
const POLL_INTERVAL_MS = 5000;

type PageState =
  | { kind: "loading" }
  | { kind: "invalid_reference" }
  | { kind: "offline" }
  | { kind: "not_found" }
  | { kind: "error"; message: string }
  | { kind: "loaded"; transaction: TransactionDetail };

export function TransactionStatusClient() {
  const params = useParams<{ reference: string }>();
  const reference = decodeURIComponent(params.reference ?? "");
  const isValidReference = reference !== "" && REFERENCE_PATTERN.test(reference);
  const [state, setState] = useState<PageState>(() =>
    isValidReference ? { kind: "loading" } : { kind: "invalid_reference" },
  );
  const [isCheckingDelivery, setIsCheckingDelivery] = useState(false);
  const [pollExhausted, setPollExhausted] = useState(false);
  const [pollingGeneration, setPollingGeneration] = useState(0);
  const pollAttemptsRef = useRef(0);

  const loadTransaction = useCallback(
    async (options?: { silent?: boolean }) => {
      if (!reference || !REFERENCE_PATTERN.test(reference)) {
        setState({ kind: "invalid_reference" });
        return;
      }

      if (!options?.silent) {
        setState({ kind: "loading" });
      }

      try {
        const transaction = await getTransaction(reference);
        updateTransactionSessionStatus(transaction.status);
        setState({ kind: "loaded", transaction });
      } catch (error) {
        if (error instanceof ApiOfflineError) {
          setState({ kind: "offline" });
          return;
        }

        if (error instanceof ApiError && error.status === 404) {
          setState({ kind: "not_found" });
          return;
        }

        if (error instanceof ApiError) {
          setState({ kind: "error", message: error.message });
          return;
        }

        setState({
          kind: "error",
          message: "Something went wrong while loading this transaction.",
        });
      }
    },
    [reference],
  );

  useEffect(() => {
    if (!isValidReference) {
      return;
    }

    let cancelled = false;

    getTransaction(reference)
      .then((transaction) => {
        if (!cancelled) {
          updateTransactionSessionStatus(transaction.status);
          setState({ kind: "loaded", transaction });
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

        if (error instanceof ApiError && error.status === 404) {
          setState({ kind: "not_found" });
          return;
        }

        if (error instanceof ApiError) {
          setState({ kind: "error", message: error.message });
          return;
        }

        setState({
          kind: "error",
          message: "Something went wrong while loading this transaction.",
        });
      });

    return () => {
      cancelled = true;
    };
  }, [isValidReference, reference]);

  const transactionStatus =
    state.kind === "loaded" ? state.transaction.status : null;
  const pollIntervalMs =
    state.kind === "loaded" && state.transaction.poll_interval_seconds
      ? state.transaction.poll_interval_seconds * 1000
      : POLL_INTERVAL_MS;

  useEffect(() => {
    if (!isValidReference || !transactionStatus) {
      return;
    }

    if (!shouldPollTransactionStatus(transactionStatus)) {
      pollAttemptsRef.current = 0;
      return;
    }

    pollAttemptsRef.current = 0;
    let cancelled = false;
    let intervalId = 0;

    const runPoll = () => {
      if (cancelled) {
        return;
      }

      const nextAttempt = pollAttemptsRef.current + 1;
      pollAttemptsRef.current = nextAttempt;

      if (nextAttempt > MAX_POLL_ATTEMPTS) {
        setPollExhausted(true);
        window.clearInterval(intervalId);
        return;
      }

      setIsCheckingDelivery(true);

      getTransaction(reference)
        .then((transaction) => {
          if (!cancelled) {
            updateTransactionSessionStatus(transaction.status);
            setState({ kind: "loaded", transaction });

            if (!shouldPollTransactionStatus(transaction.status)) {
              window.clearInterval(intervalId);
            }
          }
        })
        .catch(() => {
          // Keep last known state during polling errors.
        })
        .finally(() => {
          if (!cancelled) {
            setIsCheckingDelivery(false);
          }
        });
    };

    intervalId = window.setInterval(runPoll, pollIntervalMs);

    return () => {
      cancelled = true;
      window.clearInterval(intervalId);
    };
  }, [isValidReference, reference, transactionStatus, pollingGeneration, pollIntervalMs]);

  if (state.kind === "invalid_reference") {
    return (
      <PageContainer>
        <ErrorStatePage
          title="Invalid transaction reference"
          message="The reference in this URL does not look valid. Check the link and try again."
          icon="warning"
        />
      </PageContainer>
    );
  }

  if (state.kind === "loading") {
    return (
      <PageContainer>
        <TransactionPageSkeleton />
      </PageContainer>
    );
  }

  if (state.kind === "offline") {
    return (
      <PageContainer>
        <ErrorStatePage
          title="Network unavailable"
          message="PAYLITY could not load your transaction details. Check your connection and try again."
          icon="offline"
          onPrimaryClick={() => void loadTransaction()}
          primaryLabel="Retry"
        />
      </PageContainer>
    );
  }

  if (state.kind === "not_found") {
    return (
      <PageContainer>
        <ErrorStatePage
          title="Transaction not found"
          message={`We could not find a transaction with reference ${reference}. It may have expired or the link is incorrect.`}
          icon="warning"
        />
      </PageContainer>
    );
  }

  if (state.kind === "error") {
    return (
      <PageContainer>
        <ErrorStatePage
          title="Unable to load transaction"
          message={state.message}
          icon="error"
          onPrimaryClick={() => void loadTransaction()}
          primaryLabel="Retry"
        />
      </PageContainer>
    );
  }

  const { transaction } = state;
  const productLabel = getReceiptProductLabel(transaction.receipt, transaction.product_type);
  const phoneDisplay = getReceiptPhoneDisplay(transaction.receipt);
  const pollingExhausted =
    pollExhausted &&
    hasPollingExhausted(
      transaction.status,
      MAX_POLL_ATTEMPTS,
      MAX_POLL_ATTEMPTS,
    );
  const awaitingDelivery = isAwaitingDelivery(transaction.status);
  const showElectricityToken =
    transaction.status === "fulfilled" &&
    transaction.product_type === "electricity";

  return (
    <PageContainer className="py-8 sm:py-12">
      <div className="animate-fade-in mx-auto w-full space-y-6">
        <header className="border-b border-border pb-5">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <PaylityLogo size="md" href="/" />
            <AdSlot type="status-banner" className="sm:max-w-sm" />
          </div>
        </header>

        <section className="text-center sm:text-left">
          <p className="text-sm font-semibold uppercase tracking-wide text-success">
            Transaction Details
          </p>
          <h1 className="mt-2 font-display text-3xl font-extrabold tracking-tight text-dark sm:text-4xl">
            {transaction.reference}
          </h1>
          <div className="mt-4 flex flex-wrap justify-center gap-2 sm:justify-start">
            <StatusBadge
              label={getPaymentBadgeLabel(transaction.status)}
              variant={getPaymentBadgeVariant(transaction.status)}
            />
            <StatusBadge
              label={getFulfillmentBadgeLabel(transaction.status)}
              variant={getFulfillmentBadgeVariant(transaction.status)}
            />
          </div>
        </section>

        {awaitingDelivery ? (
          <section
            className="rounded-2xl border border-border-green bg-success-light/40 p-5 shadow-sm"
            aria-live="polite"
          >
            {pollingExhausted ? (
              <>
                <p className="text-base font-semibold text-dark">
                  Delivery is taking longer than expected. Please contact support
                  with your reference.
                </p>
                <p className="mt-2 font-mono text-sm text-foreground/70">
                  {transaction.reference}
                </p>
                <div className="mt-4 flex flex-col gap-2 sm:flex-row">
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => {
                      pollAttemptsRef.current = 0;
                      setPollExhausted(false);
                      setPollingGeneration((value) => value + 1);
                      void loadTransaction({ silent: true });
                    }}
                  >
                    Retry Status
                  </Button>
                  <Button href="#customer-support" variant="secondary">
                    Contact Support
                  </Button>
                </div>
              </>
            ) : (
              <>
                <p className="text-base font-semibold text-dark">
                  Payment confirmed. Delivery is being processed.
                </p>
                <p className="mt-2 text-sm text-foreground/65">
                  This usually takes 30 seconds to 2 minutes.
                </p>
                <Button href="#customer-support" variant="outline" className="mt-4">
                  Contact Support
                </Button>
              </>
            )}
          </section>
        ) : null}

        {isCheckingDelivery &&
        shouldPollTransactionStatus(transaction.status) &&
        !pollingExhausted ? (
          <div
            className="flex items-center gap-3 rounded-2xl border border-border-green bg-card px-4 py-3 text-sm text-muted"
            role="status"
            aria-live="polite"
          >
            <div
              className="h-4 w-4 animate-spin rounded-full border-2 border-success/20 border-t-success"
              aria-hidden="true"
            />
            Checking delivery status...
          </div>
        ) : null}

        <section
          className="rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-7"
          aria-label="Transaction reference details"
        >
          <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h2 className="text-xs font-semibold uppercase tracking-wide text-muted">
                Transaction Reference
              </h2>
              <p className="mt-3 font-mono text-lg font-bold text-dark sm:text-xl">
                {transaction.reference}
              </p>
              <p className="mt-3 text-sm text-muted">
                {productLabel} · {phoneDisplay}
              </p>
            </div>
            <CopyButton
              value={transaction.reference}
              label="Copy"
              className="w-full sm:w-auto"
            />
          </div>
        </section>

        <TransactionReceiptCard
          reference={transaction.reference}
          productLabel={productLabel}
          customerPhone={phoneDisplay}
          customerEmail={transaction.receipt?.customer_email ?? transaction.customer_email}
          productAmount={transaction.product_amount}
          convenienceFee={transaction.convenience_fee}
          gatewayFee={transaction.gateway_fee}
          payableAmount={transaction.payable_amount}
          transactionStatus={transaction.status}
          failureReason={transaction.failure_reason}
          timestamp={transaction.receipt?.timestamp ?? transaction.updated_at}
          timestampDisplay={transaction.receipt?.timestamp_display}
          verificationUrl={transaction.receipt?.verification_url}
          printable
          showActions
        />

        {showElectricityToken ? (
          <ElectricityTokenCard
            reference={transaction.reference}
            fulfillmentDetails={transaction.fulfillment_details}
          />
        ) : null}

        <section
          className="rounded-2xl border border-border bg-card p-5 shadow-sm"
          aria-label="Delivery progress"
        >
          <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted">
            Status Timeline
          </h2>
          <TransactionTimeline phase={getTimelinePhase(transaction.status)} />
        </section>

        <div className="space-y-3 print:hidden">
          <Button href="/transactions" variant="outline" className="w-full">
            View Transaction History
          </Button>
          <BackHomeLink variant="primary">Back Home</BackHomeLink>
        </div>

        <div className="print:hidden">
          <SupportCard reference={transaction.reference} />
        </div>
      </div>

      <AppFooter className="print:hidden mt-8" />
    </PageContainer>
  );
}
