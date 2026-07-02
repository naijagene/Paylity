import { type ReactNode } from "react";

type TrustBadgeProps = {
  label: string;
  icon: ReactNode;
};

export function TrustBadge({ label, icon }: TrustBadgeProps) {
  return (
    <div className="flex flex-col items-center gap-2 text-center">
      <div className="flex h-10 w-10 items-center justify-center rounded-full bg-success/10 text-success">
        {icon}
      </div>
      <span className="text-xs font-semibold text-foreground/80 sm:text-sm">
        {label}
      </span>
    </div>
  );
}
