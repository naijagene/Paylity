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
        <header className="mb-6 pt-2">
          <div className="mb-4 flex items-center justify-between gap-4">
            {step === "review" && onBack ? (
              <button
                type="button"
                onClick={onBack}
                className="text-sm font-medium text-foreground/60 transition-colors hover:text-primary"
              >
                ← Edit details
              </button>
            ) : (
              <Link
                href="/"
                className="text-sm font-medium text-foreground/60 transition-colors hover:text-primary"
              >
                ← Back to home
              </Link>
            )}
            <span className="rounded-full bg-primary/15 px-3 py-1 text-xs font-semibold text-dark">
              {stepLabels[step]}
            </span>
          </div>

          <PaylityLogo size="md" />

          <h1 className="mt-4 text-2xl font-black tracking-tight text-dark sm:text-3xl">
            {schema.label}
          </h1>
          <p className="mt-2 text-sm text-foreground/60">
            No account needed · Guest product amount up to ₦10,000
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
