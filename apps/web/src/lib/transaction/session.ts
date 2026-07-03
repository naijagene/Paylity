import { CHECKOUT_STORAGE_KEY } from "@/lib/checkout/constants";
import { isTerminalTransactionStatus as isTerminalStatus } from "@paylity/shared/payment/statusMapper";

export const TRANSACTION_SESSION_STORAGE_KEY = "paylity-transaction-session";

export type TransactionSession = {
  reference: string;
  product_type?: string;
  status?: string;
  created_at: string;
  expires_at: string;
};

const DEFAULT_TTL_MINUTES = 10;

export function getSessionTtlMinutes(): number {
  const configured = process.env.NEXT_PUBLIC_TRANSACTION_SESSION_TTL_MINUTES;
  const parsed = configured ? Number.parseInt(configured, 10) : DEFAULT_TTL_MINUTES;

  if (!Number.isFinite(parsed) || parsed <= 0) {
    return DEFAULT_TTL_MINUTES;
  }

  return parsed;
}

export function isTerminalTransactionStatus(status: string): boolean {
  return isTerminalStatus({ status });
}

export function saveTransactionSession(
  reference: string,
  status?: string,
  productType?: string,
): void {
  if (typeof window === "undefined") {
    return;
  }

  const now = new Date();
  const expiresAt = new Date(now.getTime() + getSessionTtlMinutes() * 60 * 1000);

  const session: TransactionSession = {
    reference,
    product_type: productType,
    status,
    created_at: now.toISOString(),
    expires_at: expiresAt.toISOString(),
  };

  sessionStorage.setItem(TRANSACTION_SESSION_STORAGE_KEY, JSON.stringify(session));
}

export function updateTransactionSessionStatus(status: string): void {
  const session = getTransactionSession();

  if (!session) {
    return;
  }

  session.status = status;
  sessionStorage.setItem(
    TRANSACTION_SESSION_STORAGE_KEY,
    JSON.stringify(session),
  );
}

export function getTransactionSession(): TransactionSession | null {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = sessionStorage.getItem(TRANSACTION_SESSION_STORAGE_KEY);

    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw) as TransactionSession;

    if (!parsed.reference || !parsed.created_at || !parsed.expires_at) {
      return null;
    }

    return parsed;
  } catch {
    return null;
  }
}

export function isTransactionSessionExpired(
  session: TransactionSession | null = getTransactionSession(),
): boolean {
  if (!session) {
    return true;
  }

  return Date.now() > new Date(session.expires_at).getTime();
}

export function isTransactionSessionResumable(
  session: TransactionSession | null = getTransactionSession(),
): boolean {
  if (!session || isTransactionSessionExpired(session)) {
    return false;
  }

  if (session.status && isTerminalTransactionStatus(session.status)) {
    return false;
  }

  return true;
}

export function clearCheckoutTransactionFields(): void {
  if (typeof window === "undefined") {
    return;
  }

  try {
    const raw = sessionStorage.getItem(CHECKOUT_STORAGE_KEY);

    if (!raw) {
      return;
    }

    const parsed = JSON.parse(raw) as Record<string, unknown>;

    parsed.transactionRef = null;
    parsed.transactionInitialized = false;

    if (parsed.step === "review") {
      parsed.step = "form";
    }

    sessionStorage.setItem(CHECKOUT_STORAGE_KEY, JSON.stringify(parsed));
  } catch {
    // Ignore malformed checkout storage.
  }
}

export function clearTransactionSession(): void {
  if (typeof window === "undefined") {
    return;
  }

  sessionStorage.removeItem(TRANSACTION_SESSION_STORAGE_KEY);
  clearCheckoutTransactionFields();
}

export function pruneTransactionSession(): void {
  const session = getTransactionSession();

  if (!session) {
    return;
  }

  if (
    isTransactionSessionExpired(session) ||
    (session.status && isTerminalTransactionStatus(session.status))
  ) {
    clearTransactionSession();
  }
}

export function shouldResumeStoredTransaction(
  reference: string | null | undefined,
): boolean {
  if (!reference) {
    return false;
  }

  const session = getTransactionSession();

  if (!isTransactionSessionResumable(session)) {
    return false;
  }

  return session?.reference === reference;
}

export function resolveActiveTransactionReference(
  urlReference?: string | null,
): string | null {
  if (urlReference) {
    return urlReference;
  }

  const session = getTransactionSession();

  if (!isTransactionSessionResumable(session)) {
    return null;
  }

  return session?.reference ?? null;
}
