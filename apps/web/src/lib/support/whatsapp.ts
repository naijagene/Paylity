const PLACEHOLDER_WHATSAPP_NUMBER = "2348000000000";

export function getWhatsAppSupportUrl(): string | null {
  const configured = process.env.NEXT_PUBLIC_WHATSAPP_URL?.trim();

  if (!configured) {
    return null;
  }

  if (configured.includes(PLACEHOLDER_WHATSAPP_NUMBER)) {
    return null;
  }

  return configured;
}

export function buildWhatsAppHref(
  baseUrl: string,
  message: string,
): string {
  if (baseUrl.includes("text=")) {
    return baseUrl.replace(/text=[^&]*/, `text=${encodeURIComponent(message)}`);
  }

  const separator = baseUrl.includes("?") ? "&" : "?";

  return `${baseUrl}${separator}text=${encodeURIComponent(message)}`;
}
