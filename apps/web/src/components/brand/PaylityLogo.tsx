type PaylityLogoSize = "sm" | "md" | "lg";

type PaylityLogoProps = {
  size?: PaylityLogoSize;
  showText?: boolean;
  className?: string;
};

const sizeStyles: Record<
  PaylityLogoSize,
  { icon: string; text: string; bolt: string }
> = {
  sm: { icon: "h-8 w-8", text: "text-base", bolt: "h-3.5 w-3.5" },
  md: { icon: "h-9 w-9", text: "text-xl", bolt: "h-4 w-4" },
  lg: { icon: "h-11 w-11", text: "text-2xl", bolt: "h-5 w-5" },
};

export function PaylityLogo({
  size = "md",
  showText = true,
  className = "",
}: PaylityLogoProps) {
  const styles = sizeStyles[size];

  return (
    <div className={`inline-flex items-center gap-2.5 ${className}`}>
      <span
        className={`relative flex ${styles.icon} shrink-0 items-center justify-center rounded-xl bg-dark shadow-sm`}
        aria-hidden="true"
      >
        <span className="text-sm font-black text-primary">P</span>
        <svg
          viewBox="0 0 24 24"
          className={`absolute -right-0.5 -top-0.5 ${styles.bolt} text-success`}
          fill="currentColor"
          aria-hidden="true"
        >
          <path d="M13 2 3 14h8l-1 8 10-12h-8l1-8z" />
        </svg>
      </span>
      {showText ? (
        <span
          className={`${styles.text} font-black tracking-tight text-dark`}
        >
          PAYLITY <span className="text-primary">NG</span>
        </span>
      ) : null}
    </div>
  );
}
