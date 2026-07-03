import { type ReactNode } from "react";

type TrustBadgeProps = {
  label: string;
  icon: ReactNode;
  showDivider?: boolean;
};

export function TrustBadge({
  label,
  icon,
  showDivider = false,
}: TrustBadgeProps) {
  return (
    <div
      className={`flex flex-1 flex-col items-center gap-3 px-2 py-2 text-center sm:px-4 ${
        showDivider ? "sm:border-l sm:border-border" : ""
      }`}
    >
      <div className="flex h-11 w-11 items-center justify-center rounded-full bg-success-light text-success">
        {icon}
      </div>
      <span className="font-display text-sm font-semibold text-dark">{label}</span>
    </div>
  );
}

export function TrustStrip({ children }: { children: ReactNode }) {
  return (
    <section className="rounded-2xl border border-border bg-card px-3 py-6 shadow-sm sm:px-6">
      <div className="flex flex-col gap-6 sm:flex-row sm:items-stretch sm:justify-between">
        {children}
      </div>
    </section>
  );
}
