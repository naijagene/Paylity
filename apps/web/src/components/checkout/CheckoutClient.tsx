"use client";

import { useCallback, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { Button } from "@/components/Button";
import { CheckoutForm } from "@/components/checkout/CheckoutForm";
import { CheckoutShell } from "@/components/checkout/CheckoutShell";
import { CheckoutSummaryCard } from "@/components/checkout/CheckoutSummaryCard";
import { PaymentPendingOverlay } from "@/components/checkout/PaymentPendingOverlay";
import { ProductTabs } from "@/components/checkout/ProductTabs";
import {
  buildInitializeCheckoutPayload,
  initializeCheckout,
} from "@/lib/api/checkout";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { MOCK_METER_NAMES } from "@/lib/checkout/constants";
import { resolveProduct } from "@/lib/checkout/checkoutSchemas";
import { validateCheckoutForm } from "@/lib/checkout/checkoutValidation";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { useCheckoutState } from "@/hooks/useCheckoutState";
import type { ProductType } from "@/lib/checkout/types";

function CheckoutEngine({ product }: { product: ProductType }) {
  const {
    state,
    productAmount,
    convenienceFee,
    gatewayFee,
    payableAmount,
    selectedProductAmount,
    fieldErrors,
    setFieldErrors,
    isOverGuestLimit,
    setProduct,
    updateField,
    setStep,
    setTransactionInitialized,
    setCustomProductAmount,
    selectProductAmount,
    markMeterVerified,
    resetMeterVerification,
  } = useCheckoutState(product);

  const [isVerifyingMeter, setIsVerifyingMeter] = useState(false);
  const [isInitializing, setIsInitializing] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);
  const formRef = useRef<HTMLDivElement>(null);

  const summaryPricing = state.transactionInitialized
    ? {
        productAmount: state.productAmount,
        convenienceFee: state.convenienceFee,
        gatewayFee: state.gatewayFee,
        payableAmount: state.payableAmount,
      }
    : {
        productAmount,
        convenienceFee,
        gatewayFee,
        payableAmount,
      };

  const scrollToFirstError = useCallback(() => {
    requestAnimationFrame(() => {
      const firstError = formRef.current?.querySelector('[role="alert"]');
      firstError?.scrollIntoView({ behavior: "smooth", block: "center" });
    });
  }, []);

  const handleContinue = useCallback(() => {
    setApiError(null);

    const fields = {
      ...state.fields,
      recipientPhone: state.fields.useMyNumber
        ? state.fields.customerPhone
        : state.fields.recipientPhone,
    };

    const errors = validateCheckoutForm(product, fields, productAmount);
    setFieldErrors(errors);

    if (Object.keys(errors).length > 0) {
      scrollToFirstError();
      return;
    }

    setStep("review");
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, [
    product,
    productAmount,
    scrollToFirstError,
    setFieldErrors,
    setStep,
    state.fields,
  ]);

  const handleInitializeTransaction = useCallback(async () => {
    setApiError(null);

    const fields = {
      ...state.fields,
      recipientPhone: state.fields.useMyNumber
        ? state.fields.customerPhone
        : state.fields.recipientPhone,
    };

    const errors = validateCheckoutForm(product, fields, productAmount);
    setFieldErrors(errors);

    if (Object.keys(errors).length > 0) {
      scrollToFirstError();
      return;
    }

    setIsInitializing(true);

    try {
      const payload = buildInitializeCheckoutPayload(
        product,
        state.fields,
        productAmount,
      );
      const transaction = await initializeCheckout(payload);
      setTransactionInitialized(transaction);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      if (error instanceof ApiOfflineError || error instanceof ApiError) {
        setApiError(error.message);
      } else {
        setApiError("Something went wrong. Please try again.");
      }
    } finally {
      setIsInitializing(false);
    }
  }, [
    product,
    productAmount,
    scrollToFirstError,
    setFieldErrors,
    setTransactionInitialized,
    state.fields,
  ]);

  const handleVerifyMeter = useCallback(async () => {
    if (!state.fields.meterNumber.trim()) return;

    setIsVerifyingMeter(true);
    resetMeterVerification();

    await new Promise((resolve) => setTimeout(resolve, 800));

    markMeterVerified(MOCK_METER_NAMES.default);
    setIsVerifyingMeter(false);
  }, [markMeterVerified, resetMeterVerification, state.fields.meterNumber]);

  const handleReduceProductAmount = useCallback(() => {
    selectProductAmount(10000);
    setCustomProductAmount("");
    setApiError(null);
    setStep("form");
  }, [selectProductAmount, setCustomProductAmount, setStep]);

  const handleBackToForm = useCallback(() => {
    setApiError(null);
    setStep("form");
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, [setStep]);

  const footer =
    state.step === "form" ? (
      <div className="fixed inset-x-0 bottom-0 z-40 border-t border-dark/5 bg-white/95 px-4 py-4 backdrop-blur-sm">
        <div className="mx-auto w-full max-w-lg sm:max-w-2xl lg:max-w-4xl">
          <Button
            type="button"
            className="w-full"
            onClick={handleContinue}
            disabled={isOverGuestLimit}
          >
            Continue to Review
          </Button>
        </div>
      </div>
    ) : (
      <div className="fixed inset-x-0 bottom-0 z-40 border-t border-dark/5 bg-white/95 px-4 py-4 backdrop-blur-sm">
        <div className="mx-auto w-full max-w-lg space-y-3 sm:max-w-2xl lg:max-w-4xl">
          {apiError ? (
            <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
              {apiError}
            </p>
          ) : null}

          <Button
            type="button"
            variant="outline"
            className="w-full"
            onClick={handleBackToForm}
            disabled={isInitializing}
          >
            Edit details
          </Button>

          {state.transactionInitialized ? (
            <Button type="button" className="w-full" disabled>
              Payment integration coming next
              {summaryPricing.payableAmount > 0
                ? ` · ${formatNaira(summaryPricing.payableAmount)}`
                : ""}
            </Button>
          ) : (
            <Button
              type="button"
              className="w-full"
              onClick={handleInitializeTransaction}
              disabled={isInitializing || isOverGuestLimit}
            >
              {isInitializing
                ? "Initializing transaction..."
                : `Initialize Transaction · ${formatNaira(summaryPricing.payableAmount)}`}
            </Button>
          )}

          <p className="text-center text-xs text-foreground/50">
            🔒 Secure payment · No registration required
          </p>
        </div>
      </div>
    );

  return (
    <>
      <CheckoutShell
        product={product}
        step={state.step}
        footer={footer}
        onBack={handleBackToForm}
      >
        {state.step === "form" ? (
          <>
            <ProductTabs activeProduct={product} onChange={setProduct} />
            <div ref={formRef}>
              <CheckoutForm
                product={product}
                fields={state.fields}
                selectedProductAmount={selectedProductAmount}
                customProductAmount={state.customProductAmount}
                productAmount={productAmount}
                errors={fieldErrors}
                isOverGuestLimit={isOverGuestLimit}
                isVerifyingMeter={isVerifyingMeter}
                onFieldChange={updateField}
                onSelectProductAmount={selectProductAmount}
                onCustomProductAmountChange={setCustomProductAmount}
                onVerifyMeter={handleVerifyMeter}
                onReduceProductAmount={handleReduceProductAmount}
              />
            </div>
          </>
        ) : (
          <CheckoutSummaryCard
            product={product}
            fields={state.fields}
            productAmount={summaryPricing.productAmount}
            convenienceFee={summaryPricing.convenienceFee}
            gatewayFee={summaryPricing.gatewayFee}
            payableAmount={summaryPricing.payableAmount}
            transactionReference={state.transactionRef}
            pricingMode={state.transactionInitialized ? "confirmed" : "estimated"}
            transactionReady={state.transactionInitialized}
            isOverGuestLimit={isOverGuestLimit}
            onReduceProductAmount={handleReduceProductAmount}
          />
        )}
      </CheckoutShell>

      {isInitializing ? (
        <PaymentPendingOverlay transactionRef={state.transactionRef} />
      ) : null}
    </>
  );
}

export function CheckoutClient() {
  const searchParams = useSearchParams();
  const product = resolveProduct(searchParams.get("product"));

  return <CheckoutEngine key={product} product={product} />;
}
