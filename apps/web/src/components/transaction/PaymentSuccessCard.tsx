import { Button } from "@/components/Button";
import { AdSlot } from "@/components/ads/AdSlot";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { TransactionReceiptCard } from "@/components/transaction/TransactionReceiptCard";
import { TransactionTimeline } from "@/components/transaction/TransactionTimeline";
import { SupportCard } from "@/components/support/SupportCard";
import { SystemIdentity } from "@/components/system/SystemIdentity";
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
    <div className="animate-fade-in mx-auto w-full max-w-lg space-y-6">
      <div className="flex items-center justify-between gap-4">
        <PaylityLogo size="sm" />
      </div>

      <section
        className="overflow-hidden rounded-3xl border border-success/15 bg-gradient-to-b from-success/10 to-white p-6 text-center shadow-sm sm:p-8"
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
          className="text-2xl font-black tracking-tight text-dark sm:text-3xl"
        >
          Payment Completed Successfully
        </h1>
        <p className="mt-3 text-sm leading-relaxed text-foreground/65">
          Payment confirmed. Delivery is being processed.
        </p>
        <p className="mt-2 text-sm text-foreground/60">
          If delivery takes longer than expected, contact support with your
          reference.
        </p>
        <p className="mt-5 font-mono text-sm font-bold text-dark">{reference}</p>
      </section>

      <AdSlot type="status-banner" />

      <section
        className="rounded-3xl border border-dark/5 bg-white p-5 shadow-sm"
        aria-label="Order progress"
      >
        <h2 className="mb-4 text-sm font-semibold uppercase tracking-wide text-foreground/45">
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

      <SystemIdentity className="print:hidden pt-2" />
    </div>
  );
}
