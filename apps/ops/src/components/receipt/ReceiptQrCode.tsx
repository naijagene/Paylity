"use client";

import Image from "next/image";
import { buildReceiptQrCodeUrl } from "@/lib/receipt/buildVerificationUrl";

type ReceiptQrCodeProps = {
  verificationUrl: string;
  size?: number;
};

export function ReceiptQrCode({
  verificationUrl,
  size = 112,
}: ReceiptQrCodeProps) {
  const qrUrl = buildReceiptQrCodeUrl(verificationUrl, size);

  return (
    <div className="flex flex-col items-center gap-4 py-2">
      <Image
        src={qrUrl}
        alt="Receipt verification QR code"
        width={size}
        height={size}
        unoptimized
        className="rounded-xl border border-border bg-white p-3 shadow-sm"
      />
      <p className="max-w-xs text-center text-sm text-muted">
        Scan QR or copy verification link
      </p>
    </div>
  );
}
