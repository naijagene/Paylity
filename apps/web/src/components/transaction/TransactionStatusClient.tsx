"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useParams } from "next/navigation";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { ErrorStatePage } from "@/components/transaction/ErrorStatePage";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import { TransactionPageSkeleton } from "@/components/transaction/TransactionPageSkeleton";
import { TransactionReceiptCard } from "@/components/transaction/TransactionReceiptCard";
import { TransactionTimeline } from "@/components/transaction/TransactionTimeline";
import { WhatsAppSupportCard } from "@/components/transaction/WhatsAppSupportCard";
import { SystemIdentity } from "@/components/system/SystemIdentity";
import { getTransaction, type TransactionDetail } from "@/lib/api/transactions";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import {
  getFulfillmentBadgeLabel,
  getFulfillmentBadgeVariant,
  getPaymentBadgeLabel,
  getPaymentBadgeVariant,
  getTimelinePhase,
  PRODUCT_LABELS,
  shouldPollTransactionStatus,
} from "@/lib/transaction/display";

const REFERENCE_PATTERN = /^PYL-\d{8}-[A-Z0-9]{6}$/;
const MAX_POLL_ATTEMPTS = 24;
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

    const runPoll = () => {
      if (cancelled || pollAttemptsRef.current >= MAX_POLL_ATTEMPTS) {
        return;
      }

      pollAttemptsRef.current += 1;
      setIsCheckingDelivery(true);

      getTransaction(reference)
        .then((transaction) => {
          if (!cancelled) {
            setState({ kind: "loaded", transaction });
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

    const intervalId = window.setInterval(runPoll, POLL_INTERVAL_MS);

    return () => {
      cancelled = true;
      window.clearInterval(intervalId);
    };
  }, [isValidReference, reference, transactionStatus]);

  const handlePrint = () => {
    window.print();
  };

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
  const productLabel =
    PRODUCT_LABELS[transaction.product_type] ?? transaction.product_type;

  return (
    <PageContainer className="py-8 sm:py-12">
      <div className="animate-fade-in mx-auto w-full max-w-2xl space-y-6">
        <header className="text-center sm:text-left">
          <p className="text-sm font-semibold uppercase tracking-wide text-primary">
            Transaction Details
          </p>
          <h1 className="mt-2 text-3xl font-black tracking-tight text-foreground sm:text-4xl">
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
        </header>

        {isCheckingDelivery &&
        shouldPollTransactionStatus(transaction.status) ? (
          <div
            className="flex items-center gap-3 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-sm text-foreground/70"
            role="status"
            aria-live="polite"
          >
            <div
              className="h-4 w-4 animate-spin rounded-full border-2 border-primary/20 border-t-primary"
              aria-hidden="true"
            />
            Checking delivery status...
          </div>
        ) : null}

        <section
          className="rounded-3xl border border-dark/5 bg-white p-5 shadow-sm"
          aria-label="Reference details"
        >
          <h2 className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Reference
          </h2>
          <p className="mt-2 font-mono text-lg font-bold text-foreground">
            {transaction.reference}
          </p>
          <p className="mt-2 text-sm text-foreground/60">
            {productLabel} · {transaction.customer_phone}
          </p>
        </section>

        <TransactionReceiptCard
          reference={transaction.reference}
          productLabel={productLabel}
          customerPhone={transaction.customer_phone}
          productAmount={transaction.product_amount}
          convenienceFee={transaction.convenience_fee}
          gatewayFee={transaction.gateway_fee}
          payableAmount={transaction.payable_amount}
          transactionStatus={transaction.status}
          failureReason={transaction.failure_reason}
          printable
        />

        <section
          className="rounded-3xl border border-dark/5 bg-white p-5 shadow-sm"
          aria-label="Delivery progress"
        >
          <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Status Timeline
          </h2>
          <TransactionTimeline phase={getTimelinePhase(transaction.status)} />
        </section>

        <div className="space-y-3 print:hidden">
          <Button
            type="button"
            variant="outline"
            className="w-full"
            onClick={handlePrint}
            aria-label="Download receipt using print dialog"
          >
            Download Receipt
          </Button>
          <Button href="/" className="w-full">
            Back Home
          </Button>
        </div>

        <div className="print:hidden">
          <WhatsAppSupportCard reference={transaction.reference} />
        </div>

        <SystemIdentity className="print:hidden" />
      </div>
    </PageContainer>
  );
}
