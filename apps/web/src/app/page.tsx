import { PageContainer } from "@/components/PageContainer";
import { AdSlot } from "@/components/ads/AdSlot";
import { AirtimeIcon, DataIcon, ElectricityIcon, ServiceCard } from "@/components/ServiceCard";
import { SiteHeader } from "@/components/layout/SiteHeader";
import { AppFooter } from "@/components/system/AppFooter";
import { SupportCard } from "@/components/support/SupportCard";
import { TrustBadge, TrustStrip } from "@/components/TrustBadge";

export default function Home() {
  return (
    <main className="flex flex-1 flex-col">
      <PageContainer>
        <SiteHeader />

        <section className="mb-10 text-center sm:text-left">
          <h1 className="font-display text-[2.75rem] font-extrabold leading-[1.05] tracking-tight text-dark sm:text-6xl lg:text-[4.25rem]">
            Fast <span className="text-success">Utility</span> Payments
          </h1>
          <p className="mx-auto mt-6 max-w-3xl text-lg leading-relaxed text-muted sm:text-xl sm:leading-8">
            Buy Airtime, Data and Electricity in less than 30 seconds. No
            registration required.
          </p>
        </section>

        <section className="mb-8">
          <AdSlot type="homepage-large" />
        </section>

        <section className="mb-8 flex flex-col gap-4">
          <ServiceCard
            title="Buy Airtime"
            description="Top up any Nigerian network instantly"
            href="/checkout?product=airtime"
            icon={<AirtimeIcon />}
          />
          <ServiceCard
            title="Buy Data"
            description="Get data bundles for all networks"
            href="/checkout?product=data"
            icon={<DataIcon />}
          />
          <ServiceCard
            title="Pay Electricity"
            description="Pay your electricity bill in seconds"
            href="/checkout?product=electricity"
            icon={<ElectricityIcon />}
          />
        </section>

        <section className="mb-8 grid gap-4 sm:grid-cols-2">
          <AdSlot type="homepage-small" />
          <AdSlot type="homepage-small" />
        </section>

        <section className="mb-8" id="how-it-works">
          <TrustStrip>
            <TrustBadge
              label="Secure Payments"
              icon={
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
              }
            />
            <TrustBadge
              label="Instant Delivery"
              showDivider
              icon={
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="currentColor" aria-hidden="true">
                  <path d="M13 2 3 14h8l-1 8 10-12h-8l1-8z" />
                </svg>
              }
            />
            <TrustBadge
              label="No Registration"
              showDivider
              icon={
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                  <polyline points="22 4 12 14.01 9 11.01" />
                </svg>
              }
            />
          </TrustStrip>
        </section>

        <section className="mb-4">
          <SupportCard />
        </section>
      </PageContainer>

      <AppFooter />
    </main>
  );
}
