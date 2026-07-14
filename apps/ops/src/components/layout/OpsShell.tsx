"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { type ReactNode } from "react";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { Button } from "@/components/Button";

const NAV_ITEMS = [
  { href: "/", label: "Dashboard" },
  { href: "/transactions", label: "Transactions" },
  { href: "/reconciliation", label: "Reconciliation" },
  { href: "/finance", label: "Finance" },
  { href: "/go-live", label: "Go Live" },
  { href: "/marketing", label: "Marketing" },
  { href: "/platform", label: "Platform" },
  { href: "/monitoring", label: "Monitoring" },
  { href: "/reports", label: "Reports" },
  { href: "/profile", label: "Profile" },
] as const;

type OpsShellProps = {
  children: ReactNode;
  onLock: () => void;
};

export function OpsShell({ children, onLock }: OpsShellProps) {
  const pathname = usePathname();

  return (
    <div className="flex min-h-full flex-1 flex-col lg:flex-row">
      <div className="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-sm text-amber-900 lg:hidden">
        <strong>Internal use only.</strong> Operator console.
      </div>

      <aside className="border-b border-border bg-card lg:flex lg:w-64 lg:shrink-0 lg:flex-col lg:border-b-0 lg:border-r">
        <div className="border-b border-border px-4 py-4">
          <PaylityLogo size="sm" href="/" />
          <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-success">
            Operations Console
          </p>
          <p className="text-sm font-semibold text-dark">PAYLITY MVOC</p>
        </div>

        <nav className="flex gap-1 overflow-x-auto px-2 py-3 lg:flex-col lg:overflow-visible lg:px-3">
          {NAV_ITEMS.map((item) => {
            const active =
              item.href === "/"
                ? pathname === "/"
                : pathname.startsWith(item.href);

            return (
              <Link
                key={item.href}
                href={item.href}
                className={`whitespace-nowrap rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2 ${
                  active
                    ? "bg-success text-white"
                    : "text-muted hover:bg-dark/[0.03] hover:text-dark"
                }`}
                aria-current={active ? "page" : undefined}
              >
                {item.label}
              </Link>
            );
          })}
        </nav>

        <div className="hidden border-t border-border p-3 lg:mt-auto lg:block">
          <Button type="button" variant="outline" className="w-full" onClick={onLock}>
            Lock Console
          </Button>
        </div>
      </aside>

      <div className="flex min-h-0 flex-1 flex-col">
        <div className="hidden border-b border-amber-200 bg-amber-50 px-6 py-2 text-sm text-amber-900 lg:block">
          <strong>Internal use only.</strong> Do not share this console or access key with customers.
        </div>
        <main className="flex-1 overflow-x-hidden">{children}</main>
      </div>
    </div>
  );
}
