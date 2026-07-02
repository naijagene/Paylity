export function TransactionPageSkeleton() {
  return (
    <div
      className="animate-fade-in mx-auto w-full max-w-2xl space-y-5 py-8"
      aria-busy="true"
      aria-label="Loading transaction details"
    >
      <div className="space-y-3 text-center">
        <div className="mx-auto h-4 w-32 animate-pulse rounded-full bg-dark/10" />
        <div className="mx-auto h-8 w-64 animate-pulse rounded-2xl bg-dark/10" />
      </div>
      <div className="h-28 animate-pulse rounded-3xl bg-dark/5" />
      <div className="h-72 animate-pulse rounded-3xl bg-dark/5" />
      <div className="h-40 animate-pulse rounded-3xl bg-dark/5" />
      <div className="h-24 animate-pulse rounded-3xl bg-dark/5" />
    </div>
  );
}

export function PaymentVerificationSkeleton() {
  return (
    <div
      className="animate-fade-in mx-auto w-full max-w-md py-16 text-center"
      aria-busy="true"
      aria-label="Confirming payment"
    >
      <div className="mx-auto h-20 w-20 animate-pulse rounded-full bg-dark/10" />
      <div className="mx-auto mt-6 h-7 w-56 animate-pulse rounded-2xl bg-dark/10" />
      <div className="mx-auto mt-3 h-4 w-72 animate-pulse rounded-full bg-dark/10" />
      <div className="mx-auto mt-8 h-4 w-40 animate-pulse rounded-full bg-dark/10" />
    </div>
  );
}
