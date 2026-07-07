import { Button } from "@/components/Button";
import { getSupportEmailHref } from "@/lib/support/contact";
import {
  buildWhatsAppHref,
  getWhatsAppSupportUrl,
} from "@/lib/support/whatsapp";

type GuestLimitBannerProps = {
  onReduceProductAmount?: () => void;
  variant?: "otp" | "hard-limit";
};

export function GuestLimitBanner({
  onReduceProductAmount,
  variant = "hard-limit",
}: GuestLimitBannerProps) {
  const whatsappUrl = getWhatsAppSupportUrl();
  const isHardLimit = variant === "hard-limit";

  return (
    <div className="rounded-2xl border border-error/20 bg-error/5 p-4">
      <p className="text-sm font-bold text-foreground">
        {isHardLimit ? "Guest limit reached" : "Phone verification required"}
      </p>
      <p className="mt-2 text-sm text-foreground/70">
        {isHardLimit
          ? "Purchases above ₦20,000 require a customer account. Customer accounts are coming soon."
          : "Purchases above ₦10,000 require a one-time phone verification before payment."}
      </p>
      <div className="mt-4 flex flex-col gap-2 sm:flex-row">
        {onReduceProductAmount ? (
          <Button
            type="button"
            variant="outline"
            className="w-full sm:w-auto"
            onClick={onReduceProductAmount}
          >
            Reduce product amount
          </Button>
        ) : null}
        {whatsappUrl ? (
          <Button
            href={buildWhatsAppHref(
              whatsappUrl,
              "Hi PAYLITY NG, I need help with a guest checkout limit.",
            )}
            target="_blank"
            rel="noopener noreferrer"
            className="w-full bg-success text-white hover:bg-[#0ea371] sm:w-auto"
          >
            WhatsApp Support
          </Button>
        ) : null}
        <Button
          href={getSupportEmailHref()}
          variant={whatsappUrl ? "outline" : "secondary"}
          className="w-full sm:w-auto"
        >
          Email Support
        </Button>
      </div>
    </div>
  );
}
