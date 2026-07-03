"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { PaylityLogo } from "@/components/brand/PaylityLogo";

const NAV_LINKS = [
  { href: "/", label: "Home" },
  { href: "/checkout", label: "Pricing" },
  { href: "/#how-it-works", label: "How it works" },
  { href: "/#customer-support", label: "Help" },
];

type SiteHeaderProps = {
  className?: string;
};

export function SiteHeader({ className = "" }: SiteHeaderProps) {
  const pathname = usePathname();

  return (
    <header
      className={`mb-8 flex flex-col gap-4 border-b border-border/80 pb-5 sm:flex-row sm:items-center sm:justify-between ${className}`}
    >
      <PaylityLogo size="md" priority />

      <nav
        aria-label="Primary"
        className="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm font-medium"
      >
        {NAV_LINKS.map((link) => {
          const isActive =
            link.href === "/"
              ? pathname === "/"
              : pathname.startsWith(link.href.split("#")[0]) &&
                link.href !== "/#how-it-works" &&
                link.href !== "/#customer-support";

          return (
            <Link
              key={link.href}
              href={link.href}
              className={`transition-colors ${
                isActive
                  ? "font-semibold text-success"
                  : "text-muted hover:text-dark"
              }`}
            >
              {link.label}
            </Link>
          );
        })}
      </nav>
    </header>
  );
}
