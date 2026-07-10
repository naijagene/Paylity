import { ApiError, ApiOfflineError } from "@/lib/api/client";

import { clearOperatorKey } from "@/lib/ops/operatorKey";

export const OPERATOR_AUTH_EXPIRED_EVENT = "paylity:operator-auth-expired";

export function dispatchOperatorAuthExpired(): void {
  if (typeof window === "undefined") {
    return;
  }

  window.dispatchEvent(new CustomEvent(OPERATOR_AUTH_EXPIRED_EVENT));
}

export function handleOperatorAuthFailure(): void {
  clearOperatorKey();
  dispatchOperatorAuthExpired();
}

export function isOperatorAuthError(error: unknown): boolean {
  if (!(error instanceof ApiError)) {
    return false;
  }

  if (error.status === 401 || error.status === 403) {
    return true;
  }

  const code = error.errors?.code;

  return (
    code === "OPERATOR_ACCESS_DENIED" ||
    code === "OPERATOR_ACCESS_NOT_CONFIGURED" ||
    code === "OPERATOR_KEY_MISSING"
  );
}

export function isOperatorConnectivityError(error: unknown): boolean {
  return error instanceof ApiOfflineError;
}
