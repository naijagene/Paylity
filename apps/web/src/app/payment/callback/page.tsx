import { Suspense } from "react";
import { PaymentCallbackClient } from "@/components/payment/PaymentCallbackClient";

function PaymentCallbackFallback() {
  return (
    <main className="flex min-h-full flex-1 flex-col items-center justify-center px-4 py-16">
      <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary/20 border-t-primary" />
      <p className="mt-4 text-sm text-foreground/60">
        Confirming your payment...
      </p>
    </main>
  );
}

export default function PaymentCallbackPage() {
  return (
    <main className="flex min-h-full flex-1 flex-col">
      <Suspense fallback={<PaymentCallbackFallback />}>
        <PaymentCallbackClient />
      </Suspense>
    </main>
  );
}
