import { type ReactNode } from "react";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import {
  getBadgeState,
  getReceiptStatuses,
  toTransactionLike,
} from "@/lib/transaction/display";

type ReceiptSectionProps = {
  title: string;
  children: ReactNode;
};

function ReceiptSection({ title, children }: ReceiptSectionProps) {
  return (
    <section className="space-y-3">
      <h3 className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
        {title}
      </h3>
      <div className="space-y-2">{children}</div>
    </section>
  );
}

function ReceiptRow({
  label,
  value,
  emphasis = false,
}: {
  label: string;
  value: string;
  emphasis?: boolean;
}) {
  return (
    <div className="flex items-start justify-between gap-4 py-1.5">
      <dt className="text-sm text-foreground/60">{label}</dt>
      <dd
        className={`max-w-[58%] text-right text-sm ${
          emphasis
            ? "text-base font-black text-foreground"
            : "font-semibold text-foreground"
        } ${label === "Reference" ? "font-mono text-xs" : ""}`}
      >
        {value}
      </dd>
    </div>
  );
}

export type TransactionReceiptCardProps = {
  reference: string;
  productLabel: string;
  customerPhone: string;
  productAmount: number;
  convenienceFee: number;
  gatewayFee: number;
  payableAmount: number;
  transactionStatus: string;
  failureReason?: string | null;
  printable?: boolean;
};

export function TransactionReceiptCard({
  reference,
  productLabel,
  customerPhone,
  productAmount,
  convenienceFee,
  gatewayFee,
  payableAmount,
  transactionStatus,
  failureReason,
  printable = false,
}: TransactionReceiptCardProps) {
  const transaction = toTransactionLike(transactionStatus);
  const badges = getBadgeState(transaction);
  const receiptStatuses = getReceiptStatuses(transaction);

  return (
    <article
      id={printable ? "transaction-receipt" : undefined}
      className="animate-fade-in space-y-6 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
      aria-label="Transaction receipt"
    >
      <div className="flex items-start justify-between gap-3 border-b border-dark/5 pb-5">
        <div>
          <PaylityLogo size="sm" />
          <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Receipt
          </p>
          <p className="mt-1 font-mono text-sm font-bold text-dark">{reference}</p>
        </div>
        <div className="flex flex-wrap justify-end gap-2">
          <StatusBadge
            label={badges.payment.label}
            variant={badges.payment.variant}
          />
          <StatusBadge
            label={badges.fulfillment.label}
            variant={badges.fulfillment.variant}
          />
        </div>
      </div>

      <ReceiptSection title="Transaction">
        <dl>
          <ReceiptRow label="Reference" value={reference} />
          <ReceiptRow label="Product" value={productLabel} />
        </dl>
      </ReceiptSection>

      <ReceiptSection title="Customer">
        <dl>
          <ReceiptRow label="Phone" value={customerPhone} />
        </dl>
      </ReceiptSection>

      <ReceiptSection title="Charges">
        <dl className="rounded-2xl bg-dark/[0.02] px-4 py-3">
          <ReceiptRow
            label="Product Amount"
            value={formatNaira(productAmount)}
          />
          <ReceiptRow
            label="Convenience Fee"
            value={formatNaira(convenienceFee)}
          />
          <ReceiptRow label="Gateway Charge" value={formatNaira(gatewayFee)} />
          <div className="my-2 border-t border-dark/5" />
          <ReceiptRow
            label="Total Paid"
            value={formatNaira(payableAmount)}
            emphasis
          />
        </dl>
      </ReceiptSection>

      <ReceiptSection title="Status">
        <dl>
          <ReceiptRow label="Payment" value={receiptStatuses.payment} />
          <ReceiptRow label="Fulfillment" value={receiptStatuses.fulfillment} />
          {failureReason ? (
            <ReceiptRow label="Failure Reason" value={failureReason} />
          ) : null}
        </dl>
      </ReceiptSection>
    </article>
  );
}
