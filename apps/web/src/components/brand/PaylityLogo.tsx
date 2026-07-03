import Link from "next/link";

type PaylityLogoSize = "sm" | "md" | "lg";

type PaylityLogoProps = {
  size?: PaylityLogoSize;
  showText?: boolean;
  darkMode?: boolean;
  href?: string;
  className?: string;
};

const sizeStyles: Record<
  PaylityLogoSize,
  {
    mark: string;
    wordmark: string;
    badge: string;
  }
> = {
  sm: { mark: "h-8 w-8", wordmark: "text-base", badge: "text-[9px] px-1.5 py-0.5" },
  md: { mark: "h-10 w-10", wordmark: "text-xl", badge: "text-[10px] px-2 py-0.5" },
  lg: { mark: "h-12 w-12", wordmark: "text-2xl", badge: "text-[11px] px-2 py-0.5" },
};

function LogoMark({ className }: { className: string }) {
  return (
    <svg
      viewBox="0 0 48 48"
      className={className}
      aria-hidden="true"
      fill="none"
    >
      <defs>
        <linearGradient id="paylity-mark-gradient" x1="24" y1="4" x2="24" y2="44">
          <stop offset="0%" stopColor="#10B981" />
          <stop offset="100%" stopColor="#0F172A" />
        </linearGradient>
      </defs>
      <rect width="48" height="48" rx="14" fill="url(#paylity-mark-gradient)" />
      <path
        d="M16 10h10.5c5.2 0 8.5 2.8 8.5 7.4 0 4.1-2.6 6.8-6.8 7.1L34 38h-6.2l-5.1-11.8H22V38h-6V10Z"
        fill="white"
        fillOpacity="0.96"
      />
      <path
        d="M22 17.5h4.2c2.1 0 3.3 1 3.3 2.7 0 1.7-1.2 2.7-3.3 2.7H22v-5.4Z"
        fill="#0F172A"
        fillOpacity="0.18"
      />
      <path
        d="M30 12 24 22h4l-2 10 8-12h-4l2-8Z"
        fill="#10B981"
      />
    </svg>
  );
}

export function PaylityLogo({
  size = "md",
  showText = true,
  darkMode = false,
  href = "/",
  className = "",
}: PaylityLogoProps) {
  const styles = sizeStyles[size];
  const textClass = darkMode ? "text-white" : "text-dark";

  const content = (
    <div className={`inline-flex items-center gap-2.5 ${className}`}>
      <span className={`relative shrink-0 ${styles.mark}`}>
        <LogoMark className="h-full w-full" />
      </span>
      {showText ? (
        <span className="inline-flex items-center gap-2">
          <span
            className={`font-display ${styles.wordmark} font-bold tracking-tight ${textClass}`}
          >
            Paylity
          </span>
          <span
            className={`rounded-full bg-success font-display ${styles.badge} font-bold uppercase tracking-wide text-white`}
          >
            NG
          </span>
        </span>
      ) : null}
    </div>
  );

  if (href) {
    return (
      <Link href={href} className="inline-flex focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2 rounded-xl">
        {content}
      </Link>
    );
  }

  return content;
}
