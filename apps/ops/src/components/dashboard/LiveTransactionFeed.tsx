import Link from "next/link";
import { memo } from "react";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { getPaymentBadgeLabel, getPaymentBadgeVariant } from "@/lib/transaction/display";
import {
  formatRelativeTimestamp,
  productIcon,
  sortLiveFeedNewestFirst,
  type LiveFeedItem,
} from "@/lib/utils/dashboard";

export const LiveTransactionFeed = memo(function LiveTransactionFeed({
  items,
  loading,
}: {
  items: LiveFeedItem[];
  loading: boolean;
}) {
  const sortedItems = sortLiveFeedNewestFirst(items);

  if (loading && sortedItems.length === 0) {
    return <p className="text-sm text-muted">Loading live transactions…</p>;
  }

  if (sortedItems.length === 0) {
    return <p className="text-sm text-muted">No transactions yet today.</p>;
  }

  return (
    <div className="space-y-3">
      {sortedItems.map((item) => (
        <Link
          key={item.reference}
          href={`/transactions/${encodeURIComponent(item.reference)}`}
          className="flex items-center justify-between gap-3 rounded-2xl border border-border bg-white px-4 py-3 transition hover:border-success/30 hover:bg-success/5"
        >
          <div className="flex min-w-0 items-center gap-3">
            <span className="text-xl" aria-hidden>
              {productIcon(item.product_type)}
            </span>
            <div className="min-w-0">
              <p className="truncate font-semibold text-dark">{item.reference}</p>
              <p className="truncate text-xs text-muted">
                {item.product_type} · {item.customer_phone}
              </p>
            </div>
          </div>
          <div className="shrink-0 text-right">
            <p className="font-semibold text-dark">{formatNaira(item.payable_amount)}</p>
            <div className="mt-1 flex items-center justify-end gap-2">
              <StatusBadge
                label={getPaymentBadgeLabel(item.status)}
                variant={getPaymentBadgeVariant(item.status)}
              />
              <span className="text-xs text-muted">
                {formatRelativeTimestamp(item.created_at)}
              </span>
            </div>
          </div>
        </Link>
      ))}
    </div>
  );
});
