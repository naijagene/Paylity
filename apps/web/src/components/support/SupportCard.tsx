import { Button } from "@/components/Button";
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
      className={`animate-fade-in rounded-3xl border border-success/20 bg-gradient-to-br from-success/10 via-white to-success/5 p-5 shadow-sm ${className}`}
      aria-label="Customer support"
    >
      <p className="text-sm font-semibold text-dark">Need help?</p>
      <p className="mt-1 text-sm text-foreground/65">
        Our support team can help with payment or delivery questions. Include
        your transaction reference when contacting us.
      </p>

      <div className="mt-4 flex flex-col gap-2 sm:flex-row">
        {whatsappUrl ? (
          <Button
            href={buildWhatsAppHref(whatsappUrl, message)}
            target="_blank"
            rel="noopener noreferrer"
            className="w-full bg-success text-white hover:bg-[#0ea371] active:bg-[#0c8f64] sm:flex-1"
            aria-label="Chat with PAYLITY Support on WhatsApp"
          >
            <WhatsAppIcon />
            WhatsApp Support
          </Button>
        ) : null}
        <Button
          href={getSupportEmailHref(reference)}
          variant={whatsappUrl ? "outline" : "secondary"}
          className={`w-full sm:flex-1 ${
            whatsappUrl
              ? "border-success/25 text-success hover:border-success/40 hover:bg-success/5"
              : ""
          }`}
          aria-label={`Email PAYLITY Support at ${SUPPORT_EMAIL}`}
        >
          <EmailIcon />
          Email Support
        </Button>
      </div>

      <p className="mt-3 text-xs text-foreground/50">{SUPPORT_EMAIL}</p>
    </aside>
  );
}

function WhatsAppIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      className="h-5 w-5"
      fill="currentColor"
      aria-hidden="true"
    >
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
    </svg>
  );
}

function EmailIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      className="h-5 w-5"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
      <polyline points="22,6 12,13 2,6" />
    </svg>
  );
}
