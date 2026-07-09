import type { InitializeCheckoutResponse } from "@/lib/api/checkout";

export const MISSING_PAYSTACK_REDIRECT_MESSAGE =
  "Payment could not be started. Please try again or view your transaction for details.";

export function getPaystackAuthorizationUrl(
  transaction: InitializeCheckoutResponse,
): string | null {
  const authorizationUrl = transaction.authorization_url?.trim();

  return authorizationUrl ? authorizationUrl : null;
}

export function expectsPaystackRedirect(
  transaction: InitializeCheckoutResponse,
): boolean {
  return (
    transaction.payment_provider === "paystack" ||
    transaction.status === "payment_pending"
  );
}

export function resolveCheckoutPaymentAction(
  transaction: InitializeCheckoutResponse,
): "redirect" | "fallback" | "complete" {
  if (getPaystackAuthorizationUrl(transaction)) {
    return "redirect";
  }

  if (expectsPaystackRedirect(transaction)) {
    return "fallback";
  }

  return "complete";
}

export function redirectToPaystackAuthorizationUrl(authorizationUrl: string): void {
  window.location.replace(authorizationUrl);
}
