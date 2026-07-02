"use client";

import { useCallback, useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { verifyPaystackPayment } from "@/lib/api/payments";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { formatNaira } from "@/lib/checkout/formatNaira";

const PRODUCT_LABELS: Record<string, string> = {
  airtime: "Airtime",
  data: "Data",
  electricity: "Electricity",
};

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

function StatusIcon({ status }: { status: string }) {
  if (status === "payment_success") {
    return (
      <div className="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-success/10">
        <span className="text-3xl text-success">✓</span>
      </div>
    );
  }

  if (status === "payment_failed") {
    return (
      <div className="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-error/10">
        <span className="text-3xl text-error">✕</span>
      </div>
    );
  }

  return (
    <div className="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10">
      <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary/20 border-t-primary" />
    </div>
  );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-4 py-3 text-sm">
      <span className="text-foreground/60">{label}</span>
      <span className="font-medium text-foreground">{value}</span>
    </div>
  );
}

export function PaymentCallbackClient() {
  const searchParams = useSearchParams();
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
        if (!cancelled) {
          setState(mapVerificationResult(result));
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
  }, [reference]);

  if (state.kind === "missing_reference") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <StatusIcon status="payment_failed" />
        <h1 className="text-2xl font-black tracking-tight text-foreground">
          Payment reference missing
        </h1>
        <p className="mt-3 max-w-md text-sm text-foreground/60">
          We could not find a payment reference in the callback URL. Please try
          again from checkout.
        </p>
        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
          <Button href="/checkout?product=airtime">Try Again</Button>
          <Button href="/" variant="outline">
            Back Home
          </Button>
        </div>
      </PageContainer>
    );
  }

  if (state.kind === "loading") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <StatusIcon status="payment_pending" />
        <h1 className="text-2xl font-black tracking-tight text-foreground">
          Confirming your payment...
        </h1>
        <p className="mt-3 max-w-md text-sm text-foreground/60">
          Please wait while we verify your payment with Paystack.
        </p>
        {reference ? (
          <p className="mt-6 font-mono text-xs text-foreground/50">
            Ref: {reference}
          </p>
        ) : null}
      </PageContainer>
    );
  }

  if (state.kind === "offline") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <StatusIcon status="payment_pending" />
        <h1 className="text-2xl font-black tracking-tight text-foreground">
          Verification unavailable
        </h1>
        <p className="mt-3 max-w-md text-sm text-foreground/60">
          PAYLITY API is currently unavailable. Please start the backend server
          and try again.
        </p>
        <Button className="mt-8" onClick={() => void checkAgain()}>
          Check Again
        </Button>
      </PageContainer>
    );
  }

  if (state.kind === "error") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <StatusIcon status="payment_failed" />
        <h1 className="text-2xl font-black tracking-tight text-foreground">
          Verification failed
        </h1>
        <p className="mt-3 max-w-md text-sm text-foreground/60">
          {state.message}
        </p>
        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
          <Button onClick={() => void checkAgain()}>Check Again</Button>
          <Button href="/" variant="outline">
            Back Home
          </Button>
        </div>
      </PageContainer>
    );
  }

  const productLabel =
    PRODUCT_LABELS[state.productType] ?? state.productType;

  if (state.status === "payment_success") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16">
        <div className="w-full max-w-md text-center">
          <StatusIcon status={state.status} />
          <h1 className="text-2xl font-black tracking-tight text-foreground sm:text-3xl">
            Payment Successful
          </h1>
          <p className="mt-3 text-sm text-foreground/60">
            {state.paymentStatus}
          </p>

          <div className="mt-8 divide-y divide-dark/5 rounded-3xl border border-dark/5 bg-white px-5 text-left">
            <SummaryRow label="Reference" value={state.reference} />
            <SummaryRow label="Product" value={productLabel} />
            <SummaryRow
              label="Product Amount"
              value={formatNaira(state.productAmount)}
            />
            <SummaryRow
              label="Convenience Fee"
              value={formatNaira(state.convenienceFee)}
            />
            <SummaryRow
              label="Gateway Charge"
              value={formatNaira(state.gatewayFee)}
            />
            <SummaryRow
              label="Total Paid"
              value={formatNaira(state.payableAmount)}
            />
            <SummaryRow label="Status" value="Payment successful" />
            <SummaryRow label="Fulfillment" value="Coming next" />
          </div>

          <Button href="/" className="mt-8 w-full">
            Back Home
          </Button>
        </div>
      </PageContainer>
    );
  }

  if (state.status === "payment_failed") {
    return (
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <StatusIcon status={state.status} />
        <h1 className="text-2xl font-black tracking-tight text-foreground">
          Payment Failed
        </h1>
        <p className="mt-3 max-w-md text-sm text-foreground/60">
          {state.failureReason ?? state.paymentStatus}
        </p>
        <p className="mt-4 font-mono text-xs text-foreground/50">
          Ref: {state.reference}
        </p>
        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
          <Button href="/checkout?product=airtime">Try Again</Button>
          <Button href="/" variant="outline">
            Back Home
          </Button>
        </div>
      </PageContainer>
    );
  }

  return (
    <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
      <StatusIcon status={state.status} />
      <h1 className="text-2xl font-black tracking-tight text-foreground">
        Payment Pending
      </h1>
      <p className="mt-3 max-w-md text-sm text-foreground/60">
        Your payment is still being confirmed.
      </p>
      <p className="mt-4 font-mono text-xs text-foreground/50">
        Ref: {state.reference}
      </p>
      <Button className="mt-8" onClick={() => void checkAgain()}>
        Check Again
      </Button>
    </PageContainer>
  );
}
