import { Suspense } from "react";
import { TransactionStatusClient } from "@/components/transaction/TransactionStatusClient";

function TransactionStatusFallback() {
  return (
    <main className="flex min-h-full flex-1 flex-col items-center justify-center px-4 py-16">
      <div className="h-10 w-10 animate-spin rounded-full border-4 border-success/20 border-t-success" />
      <p className="mt-4 text-sm text-foreground/60">
        Loading transaction status...
      </p>
    </main>
  );
}

export default function TransactionStatusPage() {
  return (
    <main className="flex min-h-full flex-1 flex-col">
      <Suspense fallback={<TransactionStatusFallback />}>
        <TransactionStatusClient />
      </Suspense>
    </main>
  );
}
