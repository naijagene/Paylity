"use client";

import { useState } from "react";
import { Button } from "@/components/Button";
import { downloadReceipt } from "@/lib/api/receipts";

type ReceiptActionsProps = {
  reference: string;
  verificationUrl?: string | null;
  className?: string;
};

export function ReceiptActions({
  reference,
  verificationUrl,
  className = "",
}: ReceiptActionsProps) {
  const [downloading, setDownloading] = useState(false);
  const [copied, setCopied] = useState(false);

  const handleDownload = async () => {
    setDownloading(true);

    try {
      await downloadReceipt(reference);
    } catch {
      window.print();
    } finally {
      setDownloading(false);
    }
  };

  const handleCopyLink = async () => {
    if (!verificationUrl) {
      return;
    }

    await navigator.clipboard.writeText(verificationUrl);
    setCopied(true);
    window.setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div className={`flex flex-col gap-2 sm:flex-row ${className}`}>
      <Button
        type="button"
        variant="outline"
        className="flex-1"
        onClick={() => void handleDownload()}
        disabled={downloading}
      >
        {downloading ? "Preparing receipt…" : "Download Receipt"}
      </Button>
      {verificationUrl ? (
        <Button
          type="button"
          variant="secondary"
          className="flex-1"
          onClick={() => void handleCopyLink()}
        >
          {copied ? "Link Copied" : "Copy Verify Link"}
        </Button>
      ) : null}
    </div>
  );
}
