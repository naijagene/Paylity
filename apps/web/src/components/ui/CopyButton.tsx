"use client";

import { useState } from "react";
import { useToast } from "@/components/ui/ToastProvider";
import { TOAST_MESSAGES } from "@/lib/ui/toast";

type CopyButtonProps = {
  value: string;
  label?: string;
  toastMessage?: string;
  className?: string;
};

export function CopyButton({
  value,
  label = "Copy",
  toastMessage = TOAST_MESSAGES.copied,
  className = "",
}: CopyButtonProps) {
  const { showToast } = useToast();
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(true);
      showToast({ title: toastMessage, variant: "success" });
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      showToast({ title: "Unable to copy", variant: "error" });
    }
  };

  return (
    <button
      type="button"
      onClick={() => void handleCopy()}
      className={`inline-flex min-h-11 items-center justify-center rounded-xl border border-border-green bg-success-light px-4 py-2.5 text-sm font-semibold text-dark transition-colors hover:bg-success/15 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2 ${className}`}
      aria-label={`${label} ${value}`}
    >
      {copied ? "Copied" : label}
    </button>
  );
}
