export const DEFAULT_MAX_POLL_ATTEMPTS = 24;

export function shouldContinuePolling(
  status: string,
  attempts: number,
  maxAttempts: number = DEFAULT_MAX_POLL_ATTEMPTS,
): boolean {
  if (attempts >= maxAttempts) {
    return false;
  }

  return status === "payment_success" || status === "fulfillment_pending";
}

export function hasPollingExhausted(
  status: string,
  attempts: number,
  maxAttempts: number = DEFAULT_MAX_POLL_ATTEMPTS,
): boolean {
  return (
    (status === "payment_success" || status === "fulfillment_pending") &&
    attempts >= maxAttempts
  );
}
