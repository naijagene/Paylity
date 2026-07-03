"use client";

import Link from "next/link";
import { useState } from "react";
import { AboutModal } from "@/components/system/AboutModal";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { getSupportEmailHref, SUPPORT_EMAIL } from "@/lib/support/contact";
import { getBuildInfo } from "@/lib/system/buildInfo";
import {
  buildWhatsAppHref,
  getWhatsAppSupportUrl,
} from "@/lib/support/whatsapp";

type AppFooterProps = {
  className?: string;
};

export function AppFooter({ className = "" }: AppFooterProps) {
  const build = getBuildInfo();
  const [expanded, setExpanded] = useState(false);
  const [aboutOpen, setAboutOpen] = useState(false);
  const currentYear = new Date().getFullYear();
  const whatsappUrl = getWhatsAppSupportUrl();

  return (
    <>
      <footer
        className={`border-t border-dark/5 bg-white py-8 text-center text-xs text-foreground/45 ${className}`}
        aria-label="Site footer"
      >
        <div className="mx-auto mb-4 flex justify-center">
          <PaylityLogo size="sm" />
        </div>
        <p>© {currentYear} {build.appName}</p>

        <div className="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
          <button
            type="button"
            onClick={() => setExpanded((value) => !value)}
            className="font-semibold text-foreground/60 underline-offset-2 transition-colors hover:text-foreground hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            aria-expanded={expanded}
            aria-controls="build-details-panel"
          >
            Version {build.appVersion}
          </button>
          <span
            className={
              build.isSandbox
                ? "rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-700"
                : "rounded-full bg-success/10 px-2 py-0.5 font-semibold text-success"
            }
          >
            {build.environment}
          </span>
          {whatsappUrl ? (
            <a
              href={buildWhatsAppHref(whatsappUrl, "Hi PAYLITY NG, I need help.")}
              target="_blank"
              rel="noopener noreferrer"
              className="transition-colors hover:text-success focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
            >
              WhatsApp
            </a>
          ) : null}
          <a
            href={getSupportEmailHref()}
            className="transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
          >
            {SUPPORT_EMAIL}
          </a>
          <button
            type="button"
            onClick={() => setAboutOpen(true)}
            className="transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
          >
            About
          </button>
          <Link
            href="/privacy"
            className="transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
          >
            Privacy
          </Link>
          <Link
            href="/terms"
            className="transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
          >
            Terms
          </Link>
        </div>

        {expanded ? (
          <div
            id="build-details-panel"
            className="animate-fade-in mx-auto mt-4 max-w-sm rounded-2xl border border-dark/5 bg-background px-4 py-3 text-left text-[11px] leading-relaxed text-foreground/55"
          >
            <p>
              <span className="font-semibold text-foreground/70">Version:</span>{" "}
              {build.appVersion}
            </p>
            <p>
              <span className="font-semibold text-foreground/70">Build:</span>{" "}
              {build.buildNumber}
            </p>
            <p>
              <span className="font-semibold text-foreground/70">Date:</span>{" "}
              {build.buildDate}
            </p>
            <p>
              <span className="font-semibold text-foreground/70">
                Environment:
              </span>{" "}
              {build.environment}
            </p>
            <p>
              <span className="font-semibold text-foreground/70">
                Git Commit:
              </span>{" "}
              {build.gitCommit || "Not set"}
            </p>
          </div>
        ) : null}
      </footer>

      <AboutModal open={aboutOpen} onClose={() => setAboutOpen(false)} />
    </>
  );
}
