import { memo } from "react";
import type { OpsDashboardAlert } from "@/lib/utils/dashboard";

const severityStyles: Record<OpsDashboardAlert["severity"], string> = {
  critical: "border-error/30 bg-error/5 text-error",
  warning: "border-amber-300 bg-amber-50 text-amber-900",
  info: "border-slate-200 bg-slate-50 text-slate-700",
};

export const AlertCenter = memo(function AlertCenter({
  alerts,
}: {
  alerts: OpsDashboardAlert[];
}) {
  if (alerts.length === 0) {
    return (
      <p className="rounded-2xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
        No active operational alerts.
      </p>
    );
  }

  return (
    <div className="space-y-3">
      {alerts.map((alert) => (
        <div
          key={`${alert.code}-${alert.message}`}
          className={`rounded-2xl border px-4 py-3 text-sm font-medium ${severityStyles[alert.severity]}`}
        >
          <p className="font-semibold uppercase tracking-wide text-xs opacity-80">
            {alert.severity}
          </p>
          <p className="mt-1">{alert.message}</p>
        </div>
      ))}
    </div>
  );
});
