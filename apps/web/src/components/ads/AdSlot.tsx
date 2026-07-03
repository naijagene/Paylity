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
    "min-h-[7rem] rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6",
  "homepage-small":
    "min-h-[6rem] rounded-2xl border border-border bg-card p-4 shadow-sm",
  "checkout-banner":
    "rounded-2xl border border-border bg-card px-4 py-3 shadow-sm",
  "status-banner":
    "rounded-2xl border border-border bg-card px-4 py-3 shadow-sm",
};

function MegaphoneIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-6 w-6" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M3 11v2a2 2 0 0 0 2 2h1l4 4V5L6 9H5a2 2 0 0 0-2 2z" />
      <path d="M15 9.5a4 4 0 0 1 0 5M17.5 6.5a7.5 7.5 0 0 1 0 11" />
    </svg>
  );
}

function ArrowIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden="true">
      <path d="M5 12h14M13 6l6 6-6 6" />
    </svg>
  );
}

export function AdSlot({ type, className = "" }: AdSlotProps) {
  const copy = AD_COPY[type];
  const compact = type === "checkout-banner" || type === "status-banner";

  return (
    <aside
      className={`${TYPE_STYLES[type]} ${className}`}
      aria-label="Advertisement placeholder"
      role="complementary"
    >
      <div className="flex items-start gap-4">
        {!compact ? (
          <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-success-light text-success">
            <MegaphoneIcon />
          </div>
        ) : null}

        <div className="min-w-0 flex-1">
          <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-success">
            {copy.tag}
          </p>
          <p
            className={`mt-1.5 font-display font-bold text-dark ${
              type === "homepage-large" ? "text-lg" : "text-sm"
            }`}
          >
            {copy.headline}
          </p>
          <p className="mt-1 text-sm text-muted">{copy.subline}</p>
        </div>

        <span className="mt-1 flex shrink-0 items-center text-success" aria-hidden="true">
          <ArrowIcon />
        </span>
      </div>
    </aside>
  );
}
