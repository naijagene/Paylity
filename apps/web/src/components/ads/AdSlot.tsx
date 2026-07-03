type AdSlotType =
  | "homepage-large"
  | "homepage-small"
  | "checkout-banner"
  | "status-banner";

type AdSlotProps = {
  type: AdSlotType;
  className?: string;
};

const AD_COPY: Record<
  AdSlotType,
  { headline: string; subline: string; tag: string }
> = {
  "homepage-large": {
    headline: "Advertise your business here",
    subline: "Reach customers making everyday payments",
    tag: "Sponsored placement",
  },
  "homepage-small": {
    headline: "Advertise your business here",
    subline: "Reach customers making everyday payments",
    tag: "Sponsored placement",
  },
  "checkout-banner": {
    headline: "Reach customers making everyday payments",
    subline: "Advertise your business here",
    tag: "Sponsored placement",
  },
  "status-banner": {
    headline: "Advertise your business here",
    subline: "Reach customers making everyday payments",
    tag: "Sponsored placement",
  },
};

const TYPE_STYLES: Record<AdSlotType, string> = {
  "homepage-large":
    "min-h-[7.5rem] rounded-3xl border border-dark/10 bg-gradient-to-br from-white via-white to-primary/5 p-6 shadow-sm",
  "homepage-small":
    "min-h-[6.5rem] rounded-2xl border border-dark/10 bg-white p-4 shadow-sm",
  "checkout-banner":
    "rounded-2xl border border-dashed border-dark/15 bg-white/80 px-4 py-3 shadow-sm",
  "status-banner":
    "rounded-2xl border border-dashed border-dark/15 bg-white px-4 py-3 shadow-sm",
};

export function AdSlot({ type, className = "" }: AdSlotProps) {
  const copy = AD_COPY[type];

  return (
    <aside
      className={`${TYPE_STYLES[type]} ${className}`}
      aria-label="Advertisement placeholder"
      role="complementary"
    >
      <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-primary">
        {copy.tag}
      </p>
      <p
        className={`mt-2 font-bold text-dark ${
          type === "homepage-large" ? "text-lg" : "text-sm"
        }`}
      >
        {copy.headline}
      </p>
      <p className="mt-1 text-sm text-foreground/60">{copy.subline}</p>
    </aside>
  );
}
