import { Button } from "@/components/Button";
import {
  buildWhatsAppHref,
  getWhatsAppSupportUrl,
} from "@/lib/support/whatsapp";

type GuestLimitBannerProps = {
  onReduceProductAmount?: () => void;
};

export function GuestLimitBanner({ onReduceProductAmount }: GuestLimitBannerProps) {
  const whatsappUrl = getWhatsAppSupportUrl();

  return (
    <div className="rounded-2xl border border-error/20 bg-error/5 p-4">
      <p className="text-sm font-bold text-foreground">Guest limit reached</p>
      <p className="mt-2 text-sm text-foreground/70">
        Guest checkout supports purchases up to ₦10,000. Please verify your phone
        number via OTP to continue.
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
            variant="secondary"
            className="w-full sm:w-auto"
          >
            Chat on WhatsApp
          </Button>
        ) : (
          <p className="self-center text-sm text-foreground/60">
            Contact PAYLITY NG support for higher purchase limits.
          </p>
        )}
      </div>
    </div>
  );
}
