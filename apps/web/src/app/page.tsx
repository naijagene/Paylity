import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { AppFooter } from "@/components/system/AppFooter";
import { ServiceCard } from "@/components/ServiceCard";
import { TrustBadge } from "@/components/TrustBadge";

const WHATSAPP_URL =
  "https://wa.me/2348000000000?text=Hi%20PAYLITY%20NG%2C%20I%20need%20help";

export default function Home() {
  return (
    <main className="flex flex-1 flex-col">
      <PageContainer>
        {/* Header / Logo */}
        <header className="mb-8 pt-2">
          <div className="inline-flex items-center gap-2">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-primary text-sm font-black text-dark">
              P
            </span>
            <span className="text-xl font-black tracking-tight text-foreground">
              PAYLITY <span className="text-primary">NG</span>
            </span>
          </div>
        </header>

        {/* Hero */}
        <section className="mb-10">
          <h1 className="text-3xl font-black leading-tight tracking-tight text-foreground sm:text-4xl lg:text-5xl">
            Fast Utility Payments
          </h1>
          <p className="mt-4 max-w-xl text-base leading-relaxed text-foreground/70 sm:text-lg">
            Buy Airtime, Data and Electricity in less than 30 seconds. No
            registration required.
          </p>
        </section>

        {/* Service Cards */}
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

        {/* Trust Strip */}
        <section className="mb-10 rounded-3xl bg-dark/[0.03] px-4 py-6 sm:px-8">
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

        {/* WhatsApp CTA */}
        <section className="mb-8 text-center">
          <p className="mb-4 text-sm text-foreground/60">
            Need help? Chat with us on WhatsApp
          </p>
          <Button
            href={WHATSAPP_URL}
            target="_blank"
            rel="noopener noreferrer"
            variant="secondary"
            className="w-full sm:w-auto"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="currentColor"
              className="h-5 w-5"
              aria-hidden
            >
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
            Chat on WhatsApp
          </Button>
        </section>
      </PageContainer>

      <AppFooter />
    </main>
  );
}
