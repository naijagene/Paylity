import Link from "next/link";
import { PageContainer } from "@/components/PageContainer";
import { AppFooter } from "@/components/system/AppFooter";

export default function PrivacyPage() {
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
            Privacy Policy
          </h1>
          <p className="mt-4 text-sm text-foreground/55">
            Last updated: July 2026 · MVP placeholder — final legal review required
            before public launch.
          </p>

          <div className="prose prose-sm mt-8 max-w-none space-y-6 text-foreground/75">
            <section>
              <h2 className="text-lg font-semibold text-foreground">
                About PAYLITY NG
              </h2>
              <p>
                PAYLITY NG helps customers purchase airtime, mobile data, and
                electricity tokens through a simple checkout experience. This
                policy describes how we handle information when you use our
                service.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Information we collect
              </h2>
              <p>
                When you complete a transaction, we may collect your phone
                number, product details, payment reference, transaction status,
                and technical information such as IP address and browser type to
                process your order and provide support.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                How we use information
              </h2>
              <p>
                We use transaction information to initialize payments, verify
                successful payment, deliver products through third-party
                providers, respond to support requests, and prevent fraud or
                abuse.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Third-party providers
              </h2>
              <p>
                Payments are processed by Paystack. Product delivery is
                fulfilled through VTPass and related network or utility
                providers. These partners process data according to their own
                policies when you use PAYLITY NG.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Transaction records
              </h2>
              <p>
                We retain transaction records for operational, accounting, and
                support purposes. You can contact support with your transaction
                reference for assistance.
              </p>
            </section>

            <section>
              <h2 className="text-lg font-semibold text-foreground">
                Contact
              </h2>
              <p>
                For privacy questions, contact PAYLITY NG support using the
                details published on our website. This document will be updated
                before full public launch following legal review.
              </p>
            </section>
          </div>
        </div>
      </PageContainer>

      <AppFooter />
    </main>
  );
}
