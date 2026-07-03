import Link from "next/link";
import {
  getSupportEmailHref,
  SUPPORT_EMAIL,
} from "@/lib/support/contact";
import {
  buildWhatsAppHref,
  getWhatsAppSupportUrl,
} from "@/lib/support/whatsapp";

type SupportCardProps = {
  reference?: string;
  className?: string;
  id?: string;
};

function HeadsetIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-8 w-8" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M3 11v2a2 2 0 0 0 2 2h1l4 4V5L6 9H5a2 2 0 0 0-2 2z" />
      <path d="M15 9.5a4 4 0 0 1 0 5M17.5 6.5a7.5 7.5 0 0 1 0 11" />
    </svg>
  );
}

function WhatsAppIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="currentColor" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
  );
}

function EmailIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
      <polyline points="22,6 12,13 2,6" />
    </svg>
  );
}

const actionCardClassName =
  "flex min-h-[4.5rem] flex-1 items-center gap-3 rounded-2xl px-4 py-3";

export function SupportCard({
  reference,
  className = "",
  id = "customer-support",
}: SupportCardProps) {
  const whatsappUrl = getWhatsAppSupportUrl();
  const message = reference
    ? `Hi PAYLITY NG, I need help with transaction ${reference}.`
    : "Hi PAYLITY NG, I need help.";

  return (
    <aside
      id={id}
      className={`animate-fade-in rounded-2xl border border-border-green bg-card p-5 shadow-sm sm:p-6 ${className}`}
      aria-label="Customer support"
    >
      <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div className="flex items-start gap-4 lg:max-w-sm">
          <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-success-light text-success">
            <HeadsetIcon />
          </div>
          <div>
            <p className="font-display text-lg font-bold text-dark">Need help?</p>
            <p className="mt-2 text-sm leading-relaxed text-muted">
              Our support team can help with payment or delivery questions.{" "}
              <span className="font-semibold text-dark">
                Include your transaction reference when contacting us.
              </span>
            </p>
          </div>
        </div>

        <div className="grid w-full gap-3 sm:grid-cols-2 lg:max-w-xl lg:justify-end">
          <Link
            href={getSupportEmailHref(reference)}
            className={`${actionCardClassName} border border-border-green bg-card transition-colors hover:border-success hover:bg-success-light/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2`}
            aria-label={`Email PAYLITY Support at ${SUPPORT_EMAIL}`}
          >
            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-success-light text-success">
              <EmailIcon />
            </span>
            <span>
              <span className="block text-sm font-semibold text-dark">Email Support</span>
              <span className="block text-sm text-muted">{SUPPORT_EMAIL}</span>
            </span>
          </Link>

          {whatsappUrl ? (
            <Link
              href={buildWhatsAppHref(whatsappUrl, message)}
              target="_blank"
              rel="noopener noreferrer"
              className={`${actionCardClassName} justify-center bg-success text-white transition-colors hover:bg-success-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2`}
              aria-label="Chat with PAYLITY Support on WhatsApp"
            >
              <WhatsAppIcon />
              <span className="text-sm font-semibold">Chat on WhatsApp</span>
            </Link>
          ) : (
            <div
              className={`${actionCardClassName} border border-border-green bg-success-light/30 text-dark`}
              aria-label="WhatsApp Support coming soon"
            >
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-success/15 text-success">
                <WhatsAppIcon />
              </span>
              <span>
                <span className="block text-sm font-semibold text-dark">WhatsApp Support</span>
                <span className="block text-sm text-muted">Coming Soon</span>
              </span>
            </div>
          )}
        </div>
      </div>

      <p className="mt-4 flex items-center justify-center gap-2 text-xs text-muted">
        <span className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-success-light text-success">
          ✓
        </span>
        We typically respond within a few minutes.
      </p>
    </aside>
  );
}
