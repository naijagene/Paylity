export const SUPPORT_EMAIL = "support@paylity.ng";

export function getSupportEmailHref(reference?: string): string {
  const subject = reference
    ? `PAYLITY NG Support — ${reference}`
    : "PAYLITY NG Support";

  const body = reference
    ? `Hi PAYLITY NG support team,%0D%0A%0D%0AI need help with transaction ${reference}.%0D%0A%0D%0A`
    : "Hi PAYLITY NG support team,%0D%0A%0D%0A";

  return `mailto:${SUPPORT_EMAIL}?subject=${encodeURIComponent(subject)}&body=${body}`;
}
