import { Button } from "@/components/Button";
import { WHATSAPP_URL } from "@/lib/checkout/constants";

type GuestLimitBannerProps = {
  onReduceAmount?: () => void;
};

export function GuestLimitBanner({ onReduceAmount }: GuestLimitBannerProps) {
  return (
    <div className="rounded-2xl border border-error/20 bg-error/5 p-4">
      <p className="text-sm font-bold text-foreground">Guest limit reached</p>
      <p className="mt-2 text-sm text-foreground/70">
        Payments above ₦10,000 require phone verification. This feature is coming
        soon.
      </p>
      <div className="mt-4 flex flex-col gap-2 sm:flex-row">
        {onReduceAmount ? (
          <Button
            type="button"
            variant="outline"
            className="w-full sm:w-auto"
            onClick={onReduceAmount}
          >
            Reduce amount
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
