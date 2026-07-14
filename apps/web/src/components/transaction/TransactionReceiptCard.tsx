import { type ReactNode } from "react";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import {
  getBadgeState,
  getReceiptStatuses,
  toTransactionLike,
} from "@/lib/transaction/display";
import { ReceiptActions } from "@/components/receipt/ReceiptActions";
import { ReceiptQrCode } from "@/components/receipt/ReceiptQrCode";
import { formatReceiptTimestamp } from "@/lib/receipt/display";

type ReceiptSectionProps = {
  title: string;
  children: ReactNode;
};

function ReceiptSection({ title, children }: ReceiptSectionProps) {
  return (
    <section className="space-y-4">
      <h3 className="text-xs font-semibold uppercase tracking-[0.14em] text-foreground/45">
        {title}
      </h3>
      <div className="space-y-2.5">{children}</div>
    </section>
  );
}

function ReceiptRow({
  label,
  value,
  emphasis = false,
  mono = false,
}: {
  label: string;
  value: string;
  emphasis?: boolean;
  mono?: boolean;
}) {
  return (
    <div className="flex items-start justify-between gap-4 py-2">
      <dt className="text-sm text-foreground/60">{label}</dt>
      <dd
        className={`max-w-[58%] text-right text-sm ${
          emphasis
            ? "text-base font-black text-foreground"
            : "font-semibold text-foreground"
        } ${mono ? "font-mono text-xs sm:text-sm" : ""}`}
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
  customerEmail?: string | null;
  productAmount: number;
  voucherDiscountAmount?: number;
  voucherCodeMasked?: string | null;
  convenienceFee: number;
  gatewayFee: number;
  payableAmount: number;
  transactionStatus: string;
  failureReason?: string | null;
  timestamp?: string | null;
  timestampDisplay?: string | null;
  verificationUrl?: string | null;
  printable?: boolean;
  showActions?: boolean;
};

export function TransactionReceiptCard({
  reference,
  productLabel,
  customerPhone,
  customerEmail,
  productAmount,
  voucherDiscountAmount = 0,
  voucherCodeMasked,
  convenienceFee,
  gatewayFee,
  payableAmount,
  transactionStatus,
  failureReason,
  timestamp,
  timestampDisplay,
  verificationUrl,
  printable = false,
  showActions = false,
}: TransactionReceiptCardProps) {
  const transaction = toTransactionLike(transactionStatus);
  const badges = getBadgeState(transaction);
  const receiptStatuses = getReceiptStatuses(transaction);
  const formattedTimestamp = formatReceiptTimestamp(timestamp, timestampDisplay);

  return (
    <article
      id={printable ? "transaction-receipt" : undefined}
      className="animate-fade-in space-y-8 rounded-3xl border border-border bg-card p-6 shadow-sm sm:space-y-9 sm:p-8"
      aria-label="Transaction receipt"
    >
      <div className="flex items-start justify-between gap-4 border-b border-dark/5 pb-6">
        <div className="min-w-0 flex-1">
          <PaylityLogo size="receipt" />
          <p className="mt-4 text-xs font-semibold uppercase tracking-[0.14em] text-foreground/45">
            Receipt
          </p>
          <h2 className="mt-2 font-display text-xl font-extrabold text-dark sm:text-2xl">
            {productLabel}
          </h2>
          <p className="mt-3 font-mono text-sm font-bold text-dark sm:text-base">
            {reference}
          </p>
          {formattedTimestamp ? (
            <p className="mt-2 text-sm text-muted">{formattedTimestamp}</p>
          ) : null}
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

      <ReceiptSection title="Customer">
        <dl>
          <ReceiptRow label="Phone" value={customerPhone || "—"} />
          {customerEmail ? (
            <ReceiptRow label="Email" value={customerEmail} />
          ) : null}
        </dl>
      </ReceiptSection>

      <ReceiptSection title="Charges">
        <dl className="rounded-2xl bg-dark/[0.02] px-4 py-4 sm:px-5">
          <ReceiptRow
            label="Product Amount"
            value={formatNaira(productAmount)}
          />
          {voucherDiscountAmount > 0 ? (
            <>
              <ReceiptRow
                label="Voucher Discount"
                value={`-${formatNaira(voucherDiscountAmount)}`}
              />
              {voucherCodeMasked ? (
                <ReceiptRow label="Voucher" value={voucherCodeMasked} />
              ) : null}
            </>
          ) : null}
          <ReceiptRow
            label="Convenience Fee"
            value={formatNaira(convenienceFee)}
          />
          <ReceiptRow label="Payment Processing Fee" value={formatNaira(gatewayFee)} />
          <div className="my-3 border-t border-dark/5" />
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

      {verificationUrl ? (
        <ReceiptSection title="Verify Receipt Authenticity">
          <ReceiptQrCode verificationUrl={verificationUrl} />
        </ReceiptSection>
      ) : null}

      {showActions ? (
        <ReceiptActions
          reference={reference}
          verificationUrl={verificationUrl}
          className="print:hidden"
        />
      ) : null}
    </article>
  );
}
