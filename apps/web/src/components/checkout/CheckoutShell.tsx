import { type ReactNode } from "react";
import Link from "next/link";
import { PageContainer } from "@/components/PageContainer";
import { AdSlot } from "@/components/ads/AdSlot";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { getProductSchema } from "@/lib/checkout/checkoutSchemas";
import type { CheckoutStep, ProductType } from "@/lib/checkout/types";

type CheckoutShellProps = {
  product: ProductType;
  step: CheckoutStep;
  children: ReactNode;
  footer?: ReactNode;
  onBack?: () => void;
};

const stepLabels: Record<CheckoutStep, string> = {
  form: "Enter details",
  review: "Review payment",
  otp: "Verify phone",
  processing: "Processing",
};

export function CheckoutShell({
  product,
  step,
  children,
  footer,
  onBack,
}: CheckoutShellProps) {
  const schema = getProductSchema(product);

  return (
    <main className="flex min-h-full flex-1 flex-col pb-28">
      <PageContainer>
        <header className="mb-6 border-b border-border pb-5">
          <div className="mb-5 flex items-center justify-between gap-4">
            {onBack ? (
              <button
                type="button"
                onClick={onBack}
                className="text-sm font-medium text-muted transition-colors hover:text-success"
              >
                ← {step === "otp" ? "Back to review" : "Edit details"}
              </button>
            ) : (
              <Link
                href="/"
                className="text-sm font-medium text-muted transition-colors hover:text-success"
              >
                ← Back to home
              </Link>
            )}
            <span className="rounded-full bg-success-light px-3 py-1 text-xs font-semibold text-success-dark">
              {stepLabels[step]}
            </span>
          </div>

          <PaylityLogo size="md" href="/" />

          <h1 className="mt-5 font-display text-3xl font-extrabold tracking-tight text-dark sm:text-4xl">
            {schema.label}
          </h1>
          <p className="mt-2 text-sm text-muted">
            No account needed · Guest checkout up to ₦20,000 with phone verification
          </p>
        </header>

        <div className="mb-6">
          <AdSlot type="checkout-banner" />
        </div>

        {children}
      </PageContainer>

      {footer}
    </main>
  );
}
