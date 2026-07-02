import { Button } from "@/components/Button";
import { WHATSAPP_URL } from "@/lib/checkout/constants";

type GuestLimitBannerProps = {
  onReduceProductAmount?: () => void;
};

export function GuestLimitBanner({ onReduceProductAmount }: GuestLimitBannerProps) {
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
        <Button
          href={WHATSAPP_URL}
          target="_blank"
          rel="noopener noreferrer"
          variant="secondary"
          className="w-full sm:w-auto"
        >
          Chat on WhatsApp
        </Button>
      </div>
    </div>
  );
}
