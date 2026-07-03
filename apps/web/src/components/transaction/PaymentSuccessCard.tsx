import { Button } from "@/components/Button";
import { AdSlot } from "@/components/ads/AdSlot";
import { CONTENT_MAX_WIDTH_CLASS } from "@/components/PageContainer";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { TransactionReceiptCard } from "@/components/transaction/TransactionReceiptCard";
import { TransactionTimeline } from "@/components/transaction/TransactionTimeline";
import { SupportCard } from "@/components/support/SupportCard";
import { getTimelinePhase } from "@/lib/transaction/display";

type PaymentSuccessCardProps = {
  reference: string;
  productLabel: string;
  customerPhone: string;
  productAmount: number;
  convenienceFee: number;
  gatewayFee: number;
  payableAmount: number;
  transactionStatus?: string;
};

export function PaymentSuccessCard({
  reference,
  productLabel,
  customerPhone,
  productAmount,
  convenienceFee,
  gatewayFee,
  payableAmount,
  transactionStatus = "payment_success",
}: PaymentSuccessCardProps) {
  const handlePrint = () => {
    window.print();
  };

  return (
    <div className={`animate-fade-in mx-auto w-full ${CONTENT_MAX_WIDTH_CLASS} space-y-6`}>
      <header className="border-b border-border pb-5">
        <PaylityLogo size="md" href="/" />
      </header>

      <section
        className="overflow-hidden rounded-2xl border border-border-green bg-card p-6 text-center shadow-sm sm:p-8"
        aria-labelledby="payment-success-title"
      >
        <div
          className="mx-auto mb-5 flex h-20 w-20 items-center justify-center rounded-full bg-success text-white shadow-lg shadow-success/20"
          aria-hidden="true"
        >
          <span className="text-4xl font-bold">✓</span>
        </div>
        <h1
          id="payment-success-title"
          className="font-display text-2xl font-extrabold tracking-tight text-dark sm:text-3xl"
        >
          Payment Completed Successfully
        </h1>
        <p className="mt-3 text-sm leading-relaxed text-muted">
          Payment confirmed. Delivery is being processed.
        </p>
        <p className="mt-2 text-sm text-muted">
          This usually takes 30 seconds to 2 minutes.
        </p>
        <p className="mt-5 font-mono text-sm font-bold text-dark">{reference}</p>
      </section>

      <AdSlot type="status-banner" />

      <section
        className="rounded-2xl border border-border bg-card p-5 shadow-sm"
        aria-label="Order progress"
      >
        <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-muted">
          Order Progress
        </h2>
        <TransactionTimeline phase={getTimelinePhase(transactionStatus)} />
      </section>

      <TransactionReceiptCard
        reference={reference}
        productLabel={productLabel}
        customerPhone={customerPhone}
        productAmount={productAmount}
        convenienceFee={convenienceFee}
        gatewayFee={gatewayFee}
        payableAmount={payableAmount}
        transactionStatus={transactionStatus}
        printable
      />

      <div className="space-y-3 print:hidden">
        <Button
          href={`/transaction/${encodeURIComponent(reference)}`}
          className="w-full"
        >
          View Transaction Status
        </Button>
        <Button
          type="button"
          variant="outline"
          className="w-full"
          onClick={handlePrint}
          aria-label="Download receipt using print dialog"
        >
          Download Receipt
        </Button>
        <Button href="/" variant="outline" className="w-full">
          Back Home
        </Button>
      </div>

      <div className="print:hidden">
        <SupportCard reference={reference} />
      </div>
    </div>
  );
}
