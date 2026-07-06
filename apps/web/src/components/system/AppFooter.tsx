"use client";

import Link from "next/link";
import { useState } from "react";
import { AboutModal } from "@/components/system/AboutModal";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { CONTENT_MAX_WIDTH_CLASS } from "@/components/PageContainer";
import { getBuildInfo } from "@/lib/system/buildInfo";

type AppFooterProps = {
  className?: string;
};

const FOOTER_LINKS = [
  { href: "/checkout", label: "Services" },
  { href: "/#how-it-works", label: "How it Works" },
  { href: "#about", label: "About", button: true },
  { href: "/privacy", label: "Privacy" },
  { href: "/terms", label: "Terms" },
];

function formatDisplayVersion(version: string): string {
  return version.replace("-rc1", " RC1").replace(/-/g, ".");
}

export function AppFooter({ className = "" }: AppFooterProps) {
  const build = getBuildInfo();
  const [expanded, setExpanded] = useState(false);
  const [aboutOpen, setAboutOpen] = useState(false);
  const currentYear = new Date().getFullYear();
  const displayVersion = formatDisplayVersion(build.appVersion);

  return (
    <>
      <footer
        className={`mt-auto border-t border-border bg-card py-8 sm:py-10 ${className}`}
        aria-label="Site footer"
      >
        <div className={`mx-auto ${CONTENT_MAX_WIDTH_CLASS} px-4 sm:px-6`}>
          <div className="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <PaylityLogo size="sm" href="/" />
              <p className="mt-4 text-sm text-muted">
                Built with <span aria-label="love">❤️</span> in Nigeria
              </p>
            </div>

            <div className="text-left sm:text-right">
              <p className="text-sm font-medium text-dark">
                © {currentYear} PAYLITY NG
              </p>
              <p className="mt-2 text-sm font-semibold text-success">
                Version {displayVersion}
              </p>
              <button
                type="button"
                onClick={() => setExpanded((value) => !value)}
                className="mt-2 min-h-11 text-sm font-semibold text-muted underline-offset-2 transition-colors hover:text-dark hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2"
                aria-expanded={expanded}
                aria-controls="build-details-panel"
              >
                Build details
              </button>
            </div>
          </div>

          <div className="mt-6 flex flex-wrap items-center gap-x-5 gap-y-3 text-sm text-muted">
            {FOOTER_LINKS.map((link) =>
              link.button ? (
                <button
                  key={link.label}
                  type="button"
                  id="about"
                  onClick={() => setAboutOpen(true)}
                  className="min-h-11 transition-colors hover:text-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2"
                >
                  {link.label}
                </button>
              ) : (
                <Link
                  key={link.href}
                  href={link.href}
                  className="min-h-11 inline-flex items-center transition-colors hover:text-dark focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2"
                >
                  {link.label}
                </Link>
              ),
            )}
          </div>

          {expanded ? (
            <div
              id="build-details-panel"
              className="animate-fade-in mt-5 rounded-2xl border border-border bg-background px-4 py-4 text-left text-[11px] leading-relaxed text-muted"
            >
              <p>
                <span className="font-semibold text-dark">Version:</span>{" "}
                {build.appVersion}
              </p>
              <p>
                <span className="font-semibold text-dark">Build:</span>{" "}
                {build.buildNumber}
              </p>
              <p>
                <span className="font-semibold text-dark">Date:</span>{" "}
                {build.buildDate}
              </p>
              <p>
                <span className="font-semibold text-dark">Environment:</span>{" "}
                {build.environment}
              </p>
              <p>
                <span className="font-semibold text-dark">Git Commit:</span>{" "}
                {build.gitCommit || "Not set"}
              </p>
            </div>
          ) : null}
        </div>
      </footer>

      <AboutModal open={aboutOpen} onClose={() => setAboutOpen(false)} />
    </>
  );
}
