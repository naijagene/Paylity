import type { ReactNode } from "react";

const severityStyles = {
  critical: "border-error/30 bg-error/5 text-error",
  warning: "border-amber-300 bg-amber-50 text-amber-900",
  info: "border-slate-200 bg-slate-50 text-slate-700",
  success: "border-success/20 bg-success/5 text-success",
} as const;

type AlertCardSeverity = keyof typeof severityStyles;

export function AlertCard({
  title,
  message,
  severity = "info",
  action,
}: {
  title?: string;
  message: ReactNode;
  severity?: AlertCardSeverity;
  action?: ReactNode;
}) {
  return (
    <div className={`rounded-2xl border px-4 py-3 text-sm ${severityStyles[severity]}`}>
      <div className="flex items-start justify-between gap-3">
        <div>
          {title ? <p className="font-semibold uppercase tracking-wide text-xs opacity-80">{title}</p> : null}
          <div className={title ? "mt-1" : ""}>{message}</div>
        </div>
        {action}
      </div>
    </div>
  );
}
