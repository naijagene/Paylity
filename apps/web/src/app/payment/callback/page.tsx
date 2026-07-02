import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";

type PaymentCallbackPageProps = {
  searchParams: Promise<{
    reference?: string;
    trxref?: string;
  }>;
};

export default async function PaymentCallbackPage({
  searchParams,
}: PaymentCallbackPageProps) {
  const params = await searchParams;
  const reference = params.reference ?? params.trxref ?? null;

  return (
    <main className="flex min-h-full flex-1 flex-col">
      <PageContainer className="flex flex-1 flex-col items-center justify-center py-16 text-center">
        <div className="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-primary/10">
          <span className="text-3xl">🔒</span>
        </div>

        <h1 className="text-2xl font-black tracking-tight text-foreground sm:text-3xl">
          Payment confirmation coming next
        </h1>

        <p className="mt-3 max-w-md text-sm leading-relaxed text-foreground/60">
          Your payment has been submitted to Paystack. Product fulfillment will
          be enabled in a future milestone.
        </p>

        {reference ? (
          <p className="mt-6 rounded-2xl border border-dark/5 bg-white px-5 py-4 font-mono text-sm text-foreground/70">
            Reference: {reference}
          </p>
        ) : (
          <p className="mt-6 text-sm text-foreground/50">
            No payment reference was found in the callback URL.
          </p>
        )}

        <Button href="/" className="mt-8">
          Back to home
        </Button>
      </PageContainer>
    </main>
  );
}
