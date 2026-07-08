import { memo } from "react";

type SimpleBarChartProps = {
  items: Array<{ label: string; value: number; percentage?: number }>;
  formatValue?: (value: number) => string;
};

function formatDefault(value: number): string {
  return String(value);
}

export const SimpleBarChart = memo(function SimpleBarChart({
  items,
  formatValue = formatDefault,
}: SimpleBarChartProps) {
  const maxValue = Math.max(...items.map((item) => item.value), 1);

  return (
    <div className="space-y-3">
      {items.map((item) => {
        const width = Math.max((item.value / maxValue) * 100, item.value > 0 ? 8 : 0);

        return (
          <div key={item.label}>
            <div className="mb-1 flex items-center justify-between gap-3 text-sm">
              <span className="font-medium text-dark">{item.label}</span>
              <span className="text-muted">
                {formatValue(item.value)}
                {item.percentage != null ? ` · ${item.percentage}%` : ""}
              </span>
            </div>
            <div className="h-2 rounded-full bg-slate-100">
              <div
                className="h-2 rounded-full bg-success transition-all duration-500"
                style={{ width: `${width}%` }}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
});
