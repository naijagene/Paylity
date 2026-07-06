"use client";

import { useState } from "react";
import { Button } from "@/components/Button";
import { useToast } from "@/components/ui/ToastProvider";
import { downloadReceipt } from "@/lib/api/receipts";
import { TOAST_MESSAGES } from "@/lib/ui/toast";

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
  const { showToast } = useToast();
  const [downloading, setDownloading] = useState(false);

  const handleDownload = async () => {
    setDownloading(true);
    showToast({ title: TOAST_MESSAGES.downloadStarted, variant: "success" });

    try {
      await downloadReceipt(reference);
      showToast({ title: TOAST_MESSAGES.receiptReady, variant: "success" });
    } catch {
      window.print();
      showToast({ title: TOAST_MESSAGES.receiptReady, variant: "success" });
    } finally {
      setDownloading(false);
    }
  };

  const handleCopyLink = async () => {
    if (!verificationUrl) {
      return;
    }

    await navigator.clipboard.writeText(verificationUrl);
    showToast({
      title: TOAST_MESSAGES.verificationCopied,
      variant: "success",
    });
  };

  const handleShare = async () => {
    if (!verificationUrl) {
      return;
    }

    if (navigator.share) {
      try {
        await navigator.share({
          title: "PAYLITY Receipt",
          text: `Verify my PAYLITY receipt: ${reference}`,
          url: verificationUrl,
        });
        showToast({ title: TOAST_MESSAGES.shared, variant: "success" });
      } catch {
        // User cancelled share sheet.
      }
      return;
    }

    await handleCopyLink();
  };

  return (
    <div className={`flex flex-col gap-3 sm:flex-row ${className}`}>
      <Button
        type="button"
        variant="outline"
        className="min-h-12 flex-1"
        onClick={() => void handleDownload()}
        disabled={downloading}
      >
        {downloading ? "Preparing receipt…" : "Download Receipt"}
      </Button>
      {verificationUrl ? (
        <>
          <Button
            type="button"
            variant="secondary"
            className="min-h-12 flex-1"
            onClick={() => void handleCopyLink()}
          >
            Copy Verify Link
          </Button>
          <Button
            type="button"
            variant="outline"
            className="min-h-12 flex-1"
            onClick={() => void handleShare()}
          >
            Share Receipt
          </Button>
        </>
      ) : null}
    </div>
  );
}
