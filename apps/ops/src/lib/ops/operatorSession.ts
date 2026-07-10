const MAX_OPERATOR_KEY_LENGTH = 128;

export function normalizeOperatorKeyInput(value: string): string {
  return value.trim();
}

export function isOperatorKeyFormatValid(value: string): boolean {
  const normalized = normalizeOperatorKeyInput(value);

  if (normalized.length < 4 || normalized.length > MAX_OPERATOR_KEY_LENGTH) {
    return false;
  }

  return /^[\w-]+$/.test(normalized);
}

export const INVALID_OPERATOR_KEY_FORMAT_MESSAGE =
  "Enter a valid operator access key.";

export const INVALID_OPERATOR_KEY_MESSAGE = "Invalid operator access key.";

export const OPERATOR_API_UNAVAILABLE_MESSAGE =
  "PAYLITY API is currently unavailable. Check your connection and try again.";

export const OPERATOR_SESSION_EXPIRED_MESSAGE =
  "Your operator session has expired. Please unlock the console again.";
