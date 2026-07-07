import { type ReactNode } from "react";

export function KpiCard({
  label,
  value,
  hint,
}: {
  label: string;
  value: string | number;
  hint?: string;
}) {
  return (
    <div className="rounded-2xl border border-border bg-card p-4 shadow-sm">
      <p className="text-xs font-semibold uppercase tracking-wide text-muted">{label}</p>
      <p className="mt-2 font-display text-2xl font-extrabold text-dark">{value}</p>
      {hint ? <p className="mt-1 text-xs text-muted">{hint}</p> : null}
    </div>
  );
}

export function HealthCard({
  label,
  status,
  detail,
}: {
  label: string;
  status: string;
  detail?: string;
  className?: string;
}) {
  return (
    <div className="rounded-2xl border border-border bg-card p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-semibold text-dark">{label}</p>
          {detail ? <p className="mt-1 text-xs text-muted">{detail}</p> : null}
        </div>
        <span className="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset">
          {status}
        </span>
      </div>
    </div>
  );
}

export function SectionCard({
  title,
  children,
  action,
}: {
  title: string;
  children: ReactNode;
  action?: ReactNode;
}) {
  return (
    <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
      <div className="mb-4 flex items-center justify-between gap-3">
        <h2 className="font-display text-lg font-extrabold text-dark">{title}</h2>
        {action}
      </div>
      {children}
    </section>
  );
}
