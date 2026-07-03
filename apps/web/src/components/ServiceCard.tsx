import Link from "next/link";
import { type ReactNode } from "react";

type ServiceCardProps = {
  title: string;
  description: string;
  href: string;
  icon: ReactNode;
};

function ArrowIcon() {
  return (
    <svg
      viewBox="0 0 24 24"
      className="h-5 w-5"
      fill="none"
      stroke="currentColor"
      strokeWidth="2.5"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
    >
      <path d="M5 12h14M13 6l6 6-6 6" />
    </svg>
  );
}

export function ServiceCard({
  title,
  description,
  href,
  icon,
}: ServiceCardProps) {
  return (
    <Link
      href={href}
      className="group flex items-center gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-border-green hover:shadow-md active:scale-[0.995] sm:gap-5 sm:p-6"
    >
      <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-success-light text-success transition-colors group-hover:bg-success/15 sm:h-16 sm:w-16">
        {icon}
      </div>
      <div className="min-w-0 flex-1">
        <h3 className="font-display text-lg font-bold text-dark sm:text-xl">
          {title}
        </h3>
        <p className="mt-1 text-sm text-muted sm:text-base">{description}</p>
      </div>
      <span
        aria-hidden
        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-success transition-transform group-hover:translate-x-0.5"
      >
        <ArrowIcon />
      </span>
    </Link>
  );
}

export function AirtimeIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <rect x="7" y="2" width="10" height="20" rx="2" />
      <path d="M11 18h2" />
    </svg>
  );
}

export function DataIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-7 w-7" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M4 19V5M10 19V9M16 19V13M22 19V3" />
    </svg>
  );
}

export function ElectricityIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-7 w-7" fill="currentColor" aria-hidden="true">
      <path d="M13 2 3 14h8l-1 8 10-12h-8l1-8z" />
    </svg>
  );
}
