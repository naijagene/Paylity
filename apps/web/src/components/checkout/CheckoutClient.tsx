"use client";

import { useCallback, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { Button } from "@/components/Button";
import { CheckoutForm } from "@/components/checkout/CheckoutForm";
import { CheckoutShell } from "@/components/checkout/CheckoutShell";
import { CheckoutSummaryCard } from "@/components/checkout/CheckoutSummaryCard";
import { PaymentPendingOverlay } from "@/components/checkout/PaymentPendingOverlay";
import { ProductTabs } from "@/components/checkout/ProductTabs";
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
    setCustomProductAmount,
    selectProductAmount,
    markMeterVerified,
    resetMeterVerification,
  } = useCheckoutState(product);

  const [isVerifyingMeter, setIsVerifyingMeter] = useState(false);
  const formRef = useRef<HTMLDivElement>(null);

  const scrollToFirstError = useCallback(() => {
    requestAnimationFrame(() => {
      const firstError = formRef.current?.querySelector('[role="alert"]');
      firstError?.scrollIntoView({ behavior: "smooth", block: "center" });
    });
  }, []);

  const handleContinue = useCallback(() => {
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
    setStep("form");
  }, [selectProductAmount, setCustomProductAmount, setStep]);

  const handleBackToForm = useCallback(() => {
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
          <Button
            type="button"
            variant="outline"
            className="w-full"
            onClick={handleBackToForm}
          >
            Edit details
          </Button>
          <Button type="button" className="w-full" disabled>
            Payment integration coming next
            {payableAmount > 0 ? ` · ${formatNaira(payableAmount)}` : ""}
          </Button>
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
            productAmount={productAmount}
            convenienceFee={convenienceFee}
            gatewayFee={gatewayFee}
            payableAmount={payableAmount}
            transactionReference={state.transactionRef}
            isOverGuestLimit={isOverGuestLimit}
            onReduceProductAmount={handleReduceProductAmount}
          />
        )}
      </CheckoutShell>

      {state.step === "processing" ? (
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
