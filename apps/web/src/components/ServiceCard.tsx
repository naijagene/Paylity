import Link from "next/link";
import { type ReactNode } from "react";

type ServiceCardProps = {
  title: string;
  description: string;
  href: string;
  icon: ReactNode;
};

export function ServiceCard({
  title,
  description,
  href,
  icon,
}: ServiceCardProps) {
  return (
    <Link
      href={href}
      className="group flex items-center gap-4 rounded-3xl border border-dark/5 bg-white p-5 shadow-sm transition-all hover:border-primary/30 hover:shadow-md active:scale-[0.99] sm:p-6"
    >
      <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-primary/15 text-2xl transition-colors group-hover:bg-primary/25">
        {icon}
      </div>
      <div className="min-w-0 flex-1">
        <h3 className="text-lg font-bold text-foreground sm:text-xl">{title}</h3>
        <p className="mt-1 text-sm text-foreground/60 sm:text-base">
          {description}
        </p>
      </div>
      <span
        aria-hidden
        className="shrink-0 text-xl text-primary transition-transform group-hover:translate-x-0.5"
      >
        →
      </span>
    </Link>
  );
}
