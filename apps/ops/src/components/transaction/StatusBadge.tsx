import type { StatusBadgeVariant } from "@/lib/transaction/display";

type StatusBadgeProps = {
  label: string;
  variant: StatusBadgeVariant;
  className?: string;
};

const variantStyles: Record<StatusBadgeVariant, string> = {
  success: "bg-success/10 text-success ring-success/20",
  processing: "bg-amber-50 text-amber-700 ring-amber-200",
  failed: "bg-error/10 text-error ring-error/20",
  info: "bg-slate-100 text-slate-700 ring-slate-200",
};

export function StatusBadge({
  label,
  variant,
  className = "",
}: StatusBadgeProps) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${variantStyles[variant]} ${className}`}
      aria-label={`Status: ${label}`}
    >
      {label}
    </span>
  );
}
