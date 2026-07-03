import { Suspense } from "react";
import { CheckoutClient } from "@/components/checkout/CheckoutClient";
import { TransactionSessionCleanup } from "@/components/transaction/TransactionSessionCleanup";

function CheckoutFallback() {
  return (
    <main className="flex min-h-full flex-1 flex-col items-center justify-center px-4 py-16">
      <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary/20 border-t-primary" />
    </main>
  );
}

export default function CheckoutPage() {
  return (
    <>
      <TransactionSessionCleanup />
      <Suspense fallback={<CheckoutFallback />}>
        <CheckoutClient />
      </Suspense>
    </>
  );
}
