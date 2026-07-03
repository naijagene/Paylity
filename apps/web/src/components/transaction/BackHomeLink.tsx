"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { clearTransactionSession } from "@/lib/transaction/session";

const baseStyles =
  "inline-flex items-center justify-center gap-2 rounded-2xl px-6 py-3.5 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2";

type BackHomeLinkProps = {
  className?: string;
  children?: React.ReactNode;
  variant?: "primary" | "outline";
};

export function BackHomeLink({
  className = "",
  children = "Back Home",
  variant = "primary",
}: BackHomeLinkProps) {
  const router = useRouter();
  const variantClass =
    variant === "outline"
      ? "border border-border-green bg-card text-dark hover:border-success hover:bg-success-light w-full"
      : "bg-success text-white hover:bg-success-dark w-full";

  return (
    <Link
      href="/"
      className={`${baseStyles} ${variantClass} ${className}`}
      onClick={(event) => {
        event.preventDefault();
        clearTransactionSession();
        router.push("/");
      }}
    >
      {children}
    </Link>
  );
}
