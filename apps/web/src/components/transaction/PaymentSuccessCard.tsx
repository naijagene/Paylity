import { Button } from "@/components/Button";
import { AdSlot } from "@/components/ads/AdSlot";
import { CONTENT_MAX_WIDTH_CLASS } from "@/components/PageContainer";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { TransactionReceiptCard } from "@/components/transaction/TransactionReceiptCard";
import { TransactionTimeline } from "@/components/transaction/TransactionTimeline";
import { SupportCard } from "@/components/support/SupportCard";
import { BackHomeLink } from "@/components/transaction/BackHomeLink";
import {
  getHeroState,
  getTimelineState,
  toTransactionLike,
} from "@/lib/transaction/display";

type PaymentSuccessCardProps = {
  reference: string;
  productLabel: string;
  customerPhone: string;
  customerEmail?: string | null;
  productAmount: number;
  convenienceFee: number;
  gatewayFee: number;
  payableAmount: number;
  transactionStatus?: string;
  failureReason?: string;
  timestamp?: string | null;
  timestampDisplay?: string | null;
};

export function PaymentSuccessCard({
  reference,
  productLabel,
  customerPhone,
  customerEmail,
  productAmount,
  convenienceFee,
  gatewayFee,
  payableAmount,
  transactionStatus = "payment_success",
  failureReason,
  timestamp,
  timestampDisplay,
}: PaymentSuccessCardProps) {
  const handlePrint = () => {
    window.print();
  };

  const transaction = toTransactionLike(transactionStatus);
  const hero = getHeroState(transaction);
  const timeline = getTimelineState(transaction);
  const heroIconClass =
    hero.tone === "delivery_failed"
      ? "bg-accent text-dark"
      : "bg-success text-white shadow-lg shadow-success/20";
  const heroIconContent = hero.tone === "delivery_failed" ? "!" : "✓";
  const displayTitle =
    hero.tone === "success" ? "Payment Successful" : hero.title;

  return (
    <div className={`animate-fade-in mx-auto w-full ${CONTENT_MAX_WIDTH_CLASS} space-y-7`}>
      <header className="border-b border-border pb-5">
        <PaylityLogo size="md" href="/" />
      </header>

      <section
        className="overflow-hidden rounded-3xl border border-border-green bg-card p-7 text-center shadow-sm sm:p-9"
        aria-labelledby="payment-success-title"
      >
        <div
          className={`mx-auto mb-6 flex h-24 w-24 animate-success-pop items-center justify-center rounded-full ${heroIconClass}`}
          aria-hidden="true"
        >
          <span className="text-4xl font-bold">{heroIconContent}</span>
        </div>
        <h1
          id="payment-success-title"
          className="font-display text-2xl font-extrabold tracking-tight text-dark sm:text-3xl"
        >
          {displayTitle}
        </h1>
        <p className="mt-4 text-sm leading-relaxed text-muted">{hero.subtitle}</p>
        {hero.paragraphs.map((paragraph) => (
          <p key={paragraph} className="mt-2 text-sm leading-relaxed text-muted">
            {paragraph}
          </p>
        ))}
        {hero.detail ? (
          <p className="mt-2 text-sm text-muted">{hero.detail}</p>
        ) : null}
        {failureReason && hero.tone === "delivery_failed" ? (
          <p className="mt-3 text-sm font-medium text-error">{failureReason}</p>
        ) : null}
        <p className="mt-6 font-mono text-sm font-bold text-dark">{reference}</p>
      </section>

      <AdSlot type="status-banner" />

      <section
        className="rounded-3xl border border-border bg-card p-6 shadow-sm sm:p-7"
        aria-label="Order progress"
      >
        <h2 className="mb-5 text-xs font-semibold uppercase tracking-wide text-muted">
          Order Progress
        </h2>
        <TransactionTimeline phase={timeline.phase} animated />
      </section>

      <TransactionReceiptCard
        reference={reference}
        productLabel={productLabel}
        customerPhone={customerPhone}
        customerEmail={customerEmail}
        productAmount={productAmount}
        convenienceFee={convenienceFee}
        gatewayFee={gatewayFee}
        payableAmount={payableAmount}
        transactionStatus={transactionStatus}
        failureReason={failureReason}
        timestamp={timestamp}
        timestampDisplay={timestampDisplay}
        printable
      />

      <div className="space-y-3 print:hidden">
        {hero.showRetryDelivery ? (
          <Button
            href={`/transaction/${encodeURIComponent(reference)}`}
            className="min-h-12 w-full"
          >
            Retry Delivery
          </Button>
        ) : null}
        <Button
          href={`/transaction/${encodeURIComponent(reference)}`}
          className="min-h-12 w-full"
        >
          View Transaction Status
        </Button>
        <Button
          type="button"
          variant="outline"
          className="min-h-12 w-full"
          onClick={handlePrint}
          aria-label="Download receipt using print dialog"
        >
          Download Receipt
        </Button>
        <BackHomeLink variant="outline">Back Home</BackHomeLink>
      </div>

      <div className="print:hidden">
        <SupportCard reference={reference} />
      </div>
    </div>
  );
}
