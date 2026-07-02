import { Button } from "@/components/Button";

type ErrorStatePageProps = {
  title: string;
  message: string;
  icon?: "error" | "warning" | "offline";
  primaryLabel?: string;
  onPrimaryClick?: () => void;
  secondaryHref?: string;
  secondaryLabel?: string;
};

function ErrorIcon({ icon }: { icon: NonNullable<ErrorStatePageProps["icon"]> }) {
  const styles = {
    error: "bg-error/10 text-error",
    warning: "bg-amber-50 text-amber-700",
    offline: "bg-primary/10 text-primary",
  };

  const symbols = {
    error: "✕",
    warning: "!",
    offline: "↻",
  };

  return (
    <div
      className={`mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl ${styles[icon]}`}
      aria-hidden="true"
    >
      <span className="text-2xl font-bold">{symbols[icon]}</span>
    </div>
  );
}

export function ErrorStatePage({
  title,
  message,
  icon = "error",
  primaryLabel = "Try Again",
  onPrimaryClick,
  secondaryHref = "/",
  secondaryLabel = "Back Home",
}: ErrorStatePageProps) {
  return (
    <div
      className="animate-fade-in mx-auto w-full max-w-md py-16 text-center"
      role="alert"
      aria-live="polite"
    >
      <ErrorIcon icon={icon} />
      <h1 className="text-2xl font-black tracking-tight text-foreground sm:text-3xl">
        {title}
      </h1>
      <p className="mt-3 text-sm leading-relaxed text-foreground/60">{message}</p>
      <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
        {onPrimaryClick ? (
          <Button onClick={onPrimaryClick}>{primaryLabel}</Button>
        ) : null}
        <Button href={secondaryHref} variant="outline">
          {secondaryLabel}
        </Button>
      </div>
    </div>
  );
}
