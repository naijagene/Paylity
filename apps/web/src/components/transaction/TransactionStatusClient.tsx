"use client";

import { useCallback, useEffect, useState } from "react";
import { useParams } from "next/navigation";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { getTransaction, type TransactionDetail } from "@/lib/api/transactions";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { formatNaira } from "@/lib/checkout/formatNaira";

const PRODUCT_LABELS: Record<string, string> = {
  airtime: "Airtime",
  data: "Data",
  electricity: "Electricity",
};

const REFERENCE_PATTERN = /^PYL-\d{8}-[A-Z0-9]{6}$/;

type PageState =
  | { kind: "loading" }
  | { kind: "invalid_reference" }
  | { kind: "offline" }
  | { kind: "not_found" }
  | { kind: "error"; message: string }
  | { kind: "loaded"; transaction: TransactionDetail };

function SummaryRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4 py-3 text-sm">
      <span className="text-foreground/60">{label}</span>
      <span className="text-right font-medium text-foreground">{value}</span>
    </div>
  );
}

function paymentStatusLabel(status: string): string {
  switch (status) {
    case "payment_success":
      return "Payment successful";
    case "payment_failed":
      return "Payment failed";
    case "payment_pending":
      return "Payment pending";
    case "created":
      return "Transaction created";
    case "failed":
      return "Transaction failed";
    default:
      return status.replaceAll("_", " ");
  }
}

function fulfillmentStatusLabel(status: string): string {
  switch (status) {
    case "fulfilled":
      return "Delivered";
    case "fulfillment_pending":
      return "Delivery in progress";
    case "payment_success":
      return "Awaiting delivery";
    case "failed":
      return "Delivery failed";
    default:
      return "Not started";
  }
}

export function TransactionStatusClient() {
  const params = useParams<{ reference: string }>();
  const reference = decodeURIComponent(params.reference ?? "");
  const isValidReference = reference !== "" && REFERENCE_PATTERN.test(reference);
  const [state, setState] = useState<PageState>(() =>
    isValidReference ? { kind: "loading" } : { kind: "invalid_reference" },
  );

  const loadTransaction = useCallback(async () => {
    if (!reference || !REFERENCE_PATTERN.test(reference)) {
      setState({ kind: "invalid_reference" });
      return;
    }

    setState({ kind: "loading" });

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
  }, [reference]);

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

  if (state.kind === "invalid_reference") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <h1 className="text-2xl font-black tracking-tight text-foreground">
          Invalid transaction reference
        </h1>
        <p className="mt-3 max-w-md text-sm text-foreground/60">
          The reference in this URL does not look valid. Check the link and try
          again.
        </p>
        <Button href="/" className="mt-8">
          Back Home
        </Button>
      </PageContainer>
    );
  }

  if (state.kind === "loading") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary/20 border-t-primary" />
        <p className="mt-4 text-sm text-foreground/60">
          Loading transaction status...
        </p>
      </PageContainer>
    );
  }

  if (state.kind === "offline") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <h1 className="text-2xl font-black tracking-tight text-foreground">
          Status unavailable
        </h1>
        <p className="mt-3 max-w-md text-sm text-foreground/60">
          PAYLITY API is currently unavailable. Please start the backend server
          and try again.
        </p>
        <Button className="mt-8" onClick={() => void loadTransaction()}>
          Try Again
        </Button>
      </PageContainer>
    );
  }

  if (state.kind === "not_found") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <h1 className="text-2xl font-black tracking-tight text-foreground">
          Transaction not found
        </h1>
        <p className="mt-3 max-w-md text-sm text-foreground/60">
          We could not find a transaction with reference{" "}
          <span className="font-mono">{reference}</span>.
        </p>
        <Button href="/" className="mt-8">
          Back Home
        </Button>
      </PageContainer>
    );
  }

  if (state.kind === "error") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <h1 className="text-2xl font-black tracking-tight text-foreground">
          Unable to load transaction
        </h1>
        <p className="mt-3 max-w-md text-sm text-foreground/60">
          {state.message}
        </p>
        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
          <Button onClick={() => void loadTransaction()}>Try Again</Button>
          <Button href="/" variant="outline">
            Back Home
          </Button>
        </div>
      </PageContainer>
    );
  }

  const { transaction } = state;
  const productLabel =
    PRODUCT_LABELS[transaction.product_type] ?? transaction.product_type;

  return (
    <PageContainer className="py-10">
      <div className="mx-auto w-full max-w-md">
        <div className="mb-8 text-center">
          <p className="text-sm font-semibold uppercase tracking-wide text-primary">
            Transaction Status
          </p>
          <h1 className="mt-2 text-2xl font-black tracking-tight text-foreground sm:text-3xl">
            {transaction.reference}
          </h1>
        </div>

        <div className="divide-y divide-dark/5 rounded-3xl border border-dark/5 bg-white px-5">
          <SummaryRow label="Product" value={productLabel} />
          <SummaryRow label="Phone" value={transaction.customer_phone} />
          <SummaryRow
            label="Product Amount"
            value={formatNaira(transaction.product_amount)}
          />
          <SummaryRow
            label="Convenience Fee"
            value={formatNaira(transaction.convenience_fee)}
          />
          <SummaryRow
            label="Gateway Fee"
            value={formatNaira(transaction.gateway_fee)}
          />
          <SummaryRow
            label="Total Paid"
            value={formatNaira(transaction.payable_amount)}
          />
          <SummaryRow
            label="Payment Status"
            value={paymentStatusLabel(transaction.status)}
          />
          <SummaryRow
            label="Fulfillment Status"
            value={fulfillmentStatusLabel(transaction.status)}
          />
          {transaction.payment_provider ? (
            <SummaryRow
              label="Payment Provider"
              value={transaction.payment_provider}
            />
          ) : null}
          {transaction.fulfillment_provider ? (
            <SummaryRow
              label="Fulfillment Provider"
              value={transaction.fulfillment_provider}
            />
          ) : null}
          {transaction.fulfillment_reference ? (
            <SummaryRow
              label="Fulfillment Reference"
              value={transaction.fulfillment_reference}
            />
          ) : null}
          {transaction.failure_reason ? (
            <SummaryRow
              label="Failure Reason"
              value={transaction.failure_reason}
            />
          ) : null}
        </div>

        <Button href="/" className="mt-8 w-full">
          Back Home
        </Button>
      </div>
    </PageContainer>
  );
}
