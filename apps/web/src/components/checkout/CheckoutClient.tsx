"use client";

import { useCallback, useMemo, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { Button } from "@/components/Button";
import { CheckoutForm } from "@/components/checkout/CheckoutForm";
import { CheckoutShell } from "@/components/checkout/CheckoutShell";
import { CheckoutSummaryCard } from "@/components/checkout/CheckoutSummaryCard";
import { OtpVerificationGate } from "@/components/checkout/OtpVerificationGate";
import { PaymentPendingOverlay } from "@/components/checkout/PaymentPendingOverlay";
import { ProductTabs } from "@/components/checkout/ProductTabs";
import {
  buildInitializeCheckoutPayload,
  initializeCheckout,
} from "@/lib/api/checkout";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { MOCK_METER_NAMES } from "@/lib/checkout/constants";
import { canInitializeCheckout, getCatalogDiscos, getCatalogNetworks } from "@/lib/checkout/catalogPlans";
import { resolveProduct } from "@/lib/checkout/checkoutSchemas";
import { validateCheckoutForm } from "@/lib/checkout/checkoutValidation";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { useCheckoutState } from "@/hooks/useCheckoutState";
import { useProductCatalog } from "@/hooks/useProductCatalog";
import type { ProductType } from "@/lib/checkout/types";

const INVALID_VARIATION_MESSAGE =
  "This data plan is currently unavailable. Please choose another plan.";

function CheckoutEngine({ product }: { product: ProductType }) {
  const { catalog, loading: catalogLoading, error: catalogError } =
    useProductCatalog();

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
    requiresOtp,
    setProduct,
    updateField,
    setStep,
    setTransactionInitialized,
    setOtpVerification,
    setOtpSession,
    setCustomProductAmount,
    selectProductAmount,
    markMeterVerified,
    resetMeterVerification,
    resolveDataPlansForNetwork,
  } = useCheckoutState(product, catalog);

  const [isVerifyingMeter, setIsVerifyingMeter] = useState(false);
  const [isInitializing, setIsInitializing] = useState(false);
  const [isRedirecting, setIsRedirecting] = useState(false);
  const [apiError, setApiError] = useState<string | null>(null);
  const formRef = useRef<HTMLDivElement>(null);

  const checkoutAvailability = useMemo(
    () => canInitializeCheckout(product, catalog, catalogLoading),
    [product, catalog, catalogLoading],
  );

  const catalogNetworks = useMemo(
    () => getCatalogNetworks(catalog, process.env.NODE_ENV === "development"),
    [catalog],
  );

  const catalogDiscos = useMemo(
    () => getCatalogDiscos(catalog, process.env.NODE_ENV === "development"),
    [catalog],
  );

  const dataPlans = useMemo(
    () => resolveDataPlansForNetwork(state.fields.network),
    [resolveDataPlansForNetwork, state.fields.network],
  );

  const selectedDataPlanName = useMemo(() => {
    return dataPlans.find((plan) => plan.variationCode === state.fields.dataPlan)?.name;
  }, [dataPlans, state.fields.dataPlan]);

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

    const errors = validateCheckoutForm(product, fields, productAmount, catalog);
    setFieldErrors(errors);

    if (Object.keys(errors).length > 0) {
      scrollToFirstError();
      return;
    }

    setStep("review");
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, [
    catalog,
    product,
    productAmount,
    scrollToFirstError,
    setFieldErrors,
    setStep,
    state.fields,
  ]);

  const runCheckoutInitialize = useCallback(async (verificationTokenOverride?: string | null) => {
    setApiError(null);

    if (!checkoutAvailability.allowed) {
      setApiError(checkoutAvailability.message ?? catalogError);
      return;
    }

    const fields = {
      ...state.fields,
      recipientPhone: state.fields.useMyNumber
        ? state.fields.customerPhone
        : state.fields.recipientPhone,
    };

    const errors = validateCheckoutForm(product, fields, productAmount, catalog);
    setFieldErrors(errors);

    if (Object.keys(errors).length > 0) {
      scrollToFirstError();
      return;
    }

    setIsInitializing(true);

    let redirecting = false;

    try {
      const payload = buildInitializeCheckoutPayload(
        product,
        state.fields,
        productAmount,
        catalog,
        state.verificationToken ?? verificationTokenOverride,
      );
      const transaction = await initializeCheckout(payload);
      setTransactionInitialized(transaction);

      if (transaction.authorization_url) {
        redirecting = true;
        setIsRedirecting(true);
        window.location.assign(transaction.authorization_url);
        return;
      }

      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      if (error instanceof ApiError) {
        if (error.errors?.code === "INVALID_PRODUCT_VARIATION") {
          setApiError(INVALID_VARIATION_MESSAGE);
        } else if (error.errors?.code === "OTP_REQUIRED") {
          setStep("otp");
        } else {
          setApiError(error.message);
        }
      } else if (error instanceof ApiOfflineError) {
        setApiError(error.message);
      } else {
        setApiError("Something went wrong. Please try again.");
      }
    } finally {
      if (!redirecting) {
        setIsInitializing(false);
      }
    }
  }, [
    catalog,
    catalogError,
    checkoutAvailability.allowed,
    checkoutAvailability.message,
    product,
    productAmount,
    scrollToFirstError,
    setFieldErrors,
    setStep,
    setTransactionInitialized,
    state.fields,
    state.verificationToken,
  ]);

  const handleProceedFromReview = useCallback(() => {
    if (requiresOtp) {
      setStep("otp");
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }

    void runCheckoutInitialize();
  }, [requiresOtp, runCheckoutInitialize, setStep]);

  const handleOtpVerified = useCallback(
    (payload: {
      verificationToken: string;
      otpReference: string;
      maskedPhone: string;
      otpResendAvailableAt: string;
    }) => {
      setOtpVerification(payload);
      void runCheckoutInitialize(payload.verificationToken);
    },
    [runCheckoutInitialize, setOtpVerification],
  );

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

  const handleBackToReview = useCallback(() => {
    setApiError(null);
    setStep("review");
    window.scrollTo({ top: 0, behavior: "smooth" });
  }, [setStep]);

  const paymentBlocked =
    isOverGuestLimit ||
    !checkoutAvailability.allowed ||
    isInitializing;

  const footer =
    state.step === "form" ? (
      <div className="fixed inset-x-0 bottom-0 z-40 border-t border-dark/5 bg-white/95 px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] backdrop-blur-sm">
        <div className="mx-auto w-full max-w-lg sm:max-w-2xl lg:max-w-4xl">
          {catalogError && product === "data" ? (
            <p className="mb-3 rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
              {catalogError}
            </p>
          ) : null}
          <Button
            type="button"
            className="w-full"
            onClick={handleContinue}
            disabled={isOverGuestLimit || (product === "data" && catalogLoading)}
          >
            Continue to Review
          </Button>
        </div>
      </div>
    ) : state.step === "otp" ? (
      <div className="fixed inset-x-0 bottom-0 z-40 border-t border-dark/5 bg-white/95 px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] backdrop-blur-sm">
        <div className="mx-auto w-full max-w-lg sm:max-w-2xl lg:max-w-4xl">
          <Button
            type="button"
            variant="outline"
            className="w-full"
            onClick={handleBackToReview}
            disabled={isInitializing}
          >
            Back to review
          </Button>
        </div>
      </div>
    ) : (
      <div className="fixed inset-x-0 bottom-0 z-40 border-t border-dark/5 bg-white/95 px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] backdrop-blur-sm">
        <div className="mx-auto w-full max-w-lg space-y-3 sm:max-w-2xl lg:max-w-4xl">
          {apiError ? (
            <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
              {apiError}
            </p>
          ) : null}

          {!checkoutAvailability.allowed && checkoutAvailability.message ? (
            <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
              {checkoutAvailability.message}
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
              className="min-h-12 w-full"
              onClick={handleProceedFromReview}
              disabled={paymentBlocked}
            >
              {isInitializing
                ? "Initializing transaction..."
                : requiresOtp
                  ? "Verify phone to continue"
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
        onBack={
          state.step === "otp"
            ? handleBackToReview
            : state.step === "review"
              ? handleBackToForm
              : undefined
        }
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
                networks={catalogNetworks}
                discos={catalogDiscos}
                dataPlans={dataPlans}
                catalogLoading={catalogLoading}
                catalogError={product === "data" ? catalogError : null}
                onFieldChange={updateField}
                onSelectProductAmount={selectProductAmount}
                onCustomProductAmountChange={setCustomProductAmount}
                onVerifyMeter={handleVerifyMeter}
                onReduceProductAmount={handleReduceProductAmount}
              />
            </div>
          </>
        ) : state.step === "otp" ? (
          <OtpVerificationGate
            phone={state.fields.customerPhone}
            product={product}
            productAmount={productAmount}
            otpReference={state.otpReference}
            maskedPhone={state.maskedPhone}
            resendAvailableAt={state.otpResendAvailableAt}
            onVerified={handleOtpVerified}
            onOtpSession={setOtpSession}
          />
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
            dataPlanName={selectedDataPlanName}
            onReduceProductAmount={handleReduceProductAmount}
          />
        )}
      </CheckoutShell>

      {isInitializing || isRedirecting ? (
        <PaymentPendingOverlay
          product={product}
          transactionRef={state.transactionRef}
        />
      ) : null}
    </>
  );
}

export function CheckoutClient() {
  const searchParams = useSearchParams();
  const product = resolveProduct(searchParams.get("product"));

  return <CheckoutEngine key={product} product={product} />;
}
