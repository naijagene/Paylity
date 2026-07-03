"use client";

import Image from "next/image";
import { buildReceiptQrCodeUrl } from "@/lib/receipt/buildVerificationUrl";

type ReceiptQrCodeProps = {
  verificationUrl: string;
  size?: number;
};

export function ReceiptQrCode({
  verificationUrl,
  size = 96,
}: ReceiptQrCodeProps) {
  const qrUrl = buildReceiptQrCodeUrl(verificationUrl, size);

  return (
    <div className="flex flex-col items-center gap-2">
      <Image
        src={qrUrl}
        alt="Receipt verification QR code"
        width={size}
        height={size}
        unoptimized
        className="rounded-lg border border-border bg-white p-2"
      />
      <p className="max-w-xs break-all text-center text-xs text-muted">
        {verificationUrl}
      </p>
    </div>
  );
}
