type PaymentPendingOverlayProps = {
  transactionRef?: string | null;
};

export function PaymentPendingOverlay({
  transactionRef,
}: PaymentPendingOverlayProps) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-dark/60 px-4 backdrop-blur-sm">
      <div className="w-full max-w-sm rounded-3xl bg-white p-8 text-center shadow-xl">
        <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center">
          <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary/20 border-t-primary" />
        </div>
        <h2 className="text-xl font-bold text-foreground">Initializing your transaction</h2>
        <p className="mt-2 text-sm text-foreground/60">
          Please don&apos;t close this page
        </p>
        {transactionRef ? (
          <p className="mt-4 font-mono text-xs text-foreground/50">
            Ref: {transactionRef}
          </p>
        ) : null}
        <p className="mt-6 text-xs text-foreground/40">
          Payment integration coming next
        </p>
      </div>
    </div>
  );
}
