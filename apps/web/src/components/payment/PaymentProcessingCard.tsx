import { CONTENT_MAX_WIDTH_CLASS } from "@/components/PageContainer";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { FulfillmentProcessingTimeline } from "@/components/payment/FulfillmentProcessingTimeline";

type PaymentProcessingCardProps = {
  reference: string;
};

function SecurityCard() {
  return (
    <section
      className="rounded-2xl border border-border-green bg-success-light/40 p-5 shadow-sm"
      aria-label="Transaction security"
    >
      <p className="text-sm font-bold text-dark">Your transaction is secure</p>
      <p className="mt-2 text-sm text-foreground/70">Secure &amp; Encrypted</p>
    </section>
  );
}

function PromoCard() {
  return (
    <aside
      className="rounded-2xl border border-border bg-card p-5 shadow-sm"
      aria-label="Paylity services"
    >
      <p className="font-display text-sm font-bold text-dark">
        Pay bills, buy airtime &amp; data, and more — all in one place.
      </p>
    </aside>
  );
}

export function PaymentProcessingCard({ reference }: PaymentProcessingCardProps) {
  return (
    <div
      className={`animate-fade-in mx-auto w-full ${CONTENT_MAX_WIDTH_CLASS} space-y-6`}
    >
      <header className="border-b border-border pb-5">
        <PaylityLogo size="md" href="/" />
      </header>

      <section
        className="rounded-2xl border border-border-green bg-card p-6 text-center shadow-sm sm:p-8"
        aria-labelledby="processing-title"
        aria-live="polite"
      >
        <div
          className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-success-light"
          aria-hidden="true"
        >
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-success/20 border-t-success" />
        </div>
        <h1
          id="processing-title"
          className="font-display text-2xl font-extrabold tracking-tight text-dark sm:text-3xl"
        >
          We&apos;re processing your transaction
        </h1>
        <p className="mt-3 text-sm leading-relaxed text-muted">
          This usually takes a few seconds. Please do not close this page.
        </p>
        <p className="mt-5 font-mono text-sm font-bold text-dark">{reference}</p>
      </section>

      <section
        className="rounded-2xl border border-border bg-card p-5 shadow-sm"
        aria-label="Processing progress"
      >
        <FulfillmentProcessingTimeline />
      </section>

      <SecurityCard />
      <PromoCard />
    </div>
  );
}
