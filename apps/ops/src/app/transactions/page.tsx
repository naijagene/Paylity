import { Suspense } from "react";
import { TransactionCenterClient } from "@/components/transactions/TransactionCenterClient";

export default function TransactionsPage() {
  return (
    <Suspense fallback={<p className="px-4 py-8 text-sm text-muted">Loading transactions…</p>}>
      <TransactionCenterClient />
    </Suspense>
  );
}
