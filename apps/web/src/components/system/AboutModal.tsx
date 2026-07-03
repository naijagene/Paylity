"use client";

import { useEffect } from "react";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { SystemIdentity } from "@/components/system/SystemIdentity";
import { getBuildInfo } from "@/lib/system/buildInfo";

type AboutModalProps = {
  open: boolean;
  onClose: () => void;
};

export function AboutModal({ open, onClose }: AboutModalProps) {
  const build = getBuildInfo();

  useEffect(() => {
    if (!open) {
      return;
    }

    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        onClose();
      }
    };

    window.addEventListener("keydown", handleEscape);
    return () => window.removeEventListener("keydown", handleEscape);
  }, [open, onClose]);

  if (!open) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-dark/50 px-4 backdrop-blur-sm"
      role="dialog"
      aria-modal="true"
      aria-labelledby="about-modal-title"
      onClick={onClose}
    >
      <div
        className="animate-fade-in w-full max-w-md rounded-3xl bg-white p-6 shadow-xl"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="text-center">
          <div className="mx-auto mb-4 flex justify-center">
            <PaylityLogo size="md" href={undefined} />
          </div>
          <h2
            id="about-modal-title"
            className="text-xl font-black tracking-tight text-foreground"
          >
            {build.appName}
          </h2>
          <p className="mt-2 text-sm text-foreground/60">
            Fast, secure, and reliable utility payments for Nigeria.
          </p>
        </div>

        <ul className="mt-6 space-y-2 text-sm text-foreground/70">
          <li>⚡ Fast checkout in under 30 seconds</li>
          <li>🔒 Secure Paystack payments</li>
          <li>✓ Reliable delivery tracking</li>
        </ul>

        <div className="mt-6 rounded-2xl bg-dark/[0.03] px-4 py-3">
          <p className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Current Build
          </p>
          <SystemIdentity className="mt-2 text-left" />
        </div>

        <p className="mt-6 text-center text-xs text-foreground/40">
          © {new Date().getFullYear()} {build.appName}. All rights reserved.
        </p>

        <button
          type="button"
          onClick={onClose}
          className="mt-4 w-full rounded-2xl border border-dark/10 px-4 py-3 text-sm font-semibold text-foreground transition-colors hover:bg-dark/[0.03] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
        >
          Close
        </button>
      </div>
    </div>
  );
}
