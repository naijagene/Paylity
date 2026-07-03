export function buildVerificationUrl(token: string): string {
  if (typeof window !== "undefined") {
    return `${window.location.origin}/verify/${token}`;
  }

  const frontendUrl =
    process.env.NEXT_PUBLIC_SITE_URL ??
    process.env.NEXT_PUBLIC_FRONTEND_URL ??
    "http://localhost:3000";

  return `${frontendUrl.replace(/\/$/, "")}/verify/${token}`;
}

export function buildReceiptQrCodeUrl(content: string, size = 120): string {
  return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(content)}`;
}
