import { PageContainer } from "@/components/PageContainer";
import { AdSlot } from "@/components/ads/AdSlot";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { AppFooter } from "@/components/system/AppFooter";
import { ServiceCard } from "@/components/ServiceCard";
import { SupportCard } from "@/components/support/SupportCard";
import { TrustBadge } from "@/components/TrustBadge";

export default function Home() {
  return (
    <main className="flex flex-1 flex-col">
      <PageContainer>
        <header className="mb-8 pt-2">
          <PaylityLogo size="md" />
        </header>

        <section className="mb-10">
          <h1 className="text-3xl font-black leading-tight tracking-tight text-dark sm:text-4xl lg:text-5xl">
            Fast Utility Payments
          </h1>
          <p className="mt-4 max-w-xl text-base leading-relaxed text-foreground/70 sm:text-lg">
            Buy Airtime, Data and Electricity in less than 30 seconds. No
            registration required.
          </p>
        </section>

        <section className="mb-8">
          <AdSlot type="homepage-large" />
        </section>

        <section className="mb-10 flex flex-col gap-4">
          <ServiceCard
            title="Buy Airtime"
            description="Top up any Nigerian network instantly"
            href="/checkout?product=airtime"
            icon={<span aria-hidden>📱</span>}
          />
          <ServiceCard
            title="Buy Data"
            description="Get data bundles for all networks"
            href="/checkout?product=data"
            icon={<span aria-hidden>📶</span>}
          />
          <ServiceCard
            title="Pay Electricity"
            description="Pay your electricity bill in seconds"
            href="/checkout?product=electricity"
            icon={<span aria-hidden>⚡</span>}
          />
        </section>

        <section className="mb-10 grid gap-4 sm:grid-cols-2">
          <AdSlot type="homepage-small" />
          <AdSlot type="homepage-small" />
        </section>

        <section className="mb-10 rounded-3xl border border-dark/5 bg-white px-4 py-6 shadow-sm sm:px-8">
          <div className="grid grid-cols-3 gap-4">
            <TrustBadge
              label="Secure Payments"
              icon={
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="h-5 w-5"
                  aria-hidden
                >
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
              }
            />
            <TrustBadge
              label="Instant Delivery"
              icon={
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="h-5 w-5"
                  aria-hidden
                >
                  <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" />
                </svg>
              }
            />
            <TrustBadge
              label="No Registration"
              icon={
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="h-5 w-5"
                  aria-hidden
                >
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                  <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
              }
            />
          </div>
        </section>

        <section className="mb-8">
          <SupportCard />
        </section>
      </PageContainer>

      <AppFooter />
    </main>
  );
}
