import { type ReactNode } from "react";
import Link from "next/link";
import { PageContainer } from "@/components/PageContainer";
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

          <div className="inline-flex items-center gap-2">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-black text-dark">
              P
            </span>
            <span className="text-xl font-black tracking-tight text-foreground">
              PAYLITY <span className="text-primary">NG</span>
            </span>
          </div>

          <h1 className="mt-4 text-2xl font-black tracking-tight text-foreground sm:text-3xl">
            {schema.label}
          </h1>
          <p className="mt-2 text-sm text-foreground/60">
            No account needed · Guest payments up to ₦10,000
          </p>
        </header>

        {children}
      </PageContainer>

      {footer}
    </main>
  );
}
