import Link from "next/link";
import { PageContainer } from "@/components/PageContainer";
import { AppFooter } from "@/components/system/AppFooter";

export default function TermsPage() {
  return (
    <main className="flex flex-1 flex-col">
      <PageContainer className="py-12">
        <Link
          href="/"
          className="mb-8 inline-flex text-sm font-medium text-foreground/60 transition-colors hover:text-primary"
        >
          ← Back to home
        </Link>

        <div className="mx-auto max-w-3xl">
          <p className="text-sm font-semibold uppercase tracking-wide text-primary">
            Legal notice
          </p>
          <h1 className="mt-2 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Terms of Service
          </h1>
          <p className="mt-4 text-sm text-foreground/55">
            Last updated: July 2026 · MVP placeholder — final legal review required
            before public launch.
          </p>

          <div className="prose prose-sm mt-8 max-w-none space-y-6 text-foreground/75">
            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Acceptance
              </h2>
              <p>
                By using PAYLITY NG, you agree to these terms. If you do not
                agree, please do not use the service. These terms apply to guest
                checkout transactions without a customer account.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Service description
              </h2>
              <p>
                PAYLITY NG provides a platform to purchase airtime, mobile data,
                and electricity tokens. We facilitate payment collection and
                coordinate delivery through third-party providers.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Payments
              </h2>
              <p>
                All payments are processed securely through Paystack. A
                transaction is only considered paid after server-side
                verification. Convenience fees may apply and are shown before
                checkout.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Delivery and fulfillment
              </h2>
              <p>
                Product delivery is performed by third-party providers such as
                VTPass and network or utility operators. Delivery times may vary.
                You are responsible for entering the correct phone number, data
                plan, or meter details.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Support and disputes
              </h2>
              <p>
                For payment or delivery issues, contact PAYLITY NG support with
                your transaction reference. Refunds, where applicable, may be
                processed manually through Paystack while automated refund tooling
                is not yet available.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Limitation of liability
              </h2>
              <p>
                PAYLITY NG is provided on an as-is basis during the MVP phase.
                We are not liable for delays or failures caused by third-party
                payment or delivery providers beyond our reasonable control.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Changes
              </h2>
              <p>
                These terms may be updated before public launch. Continued use
                after updates constitutes acceptance of the revised terms.
              </p>
            </section>
          </div>
        </div>
      </PageContainer>

      <AppFooter />
    </main>
  );
}
