const STORAGE_KEY = "paylity-operator-key";

export function getOperatorKey(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  return sessionStorage.getItem(STORAGE_KEY);
}

export function setOperatorKey(key: string): void {
  sessionStorage.setItem(STORAGE_KEY, key);
}

export function clearOperatorKey(): void {
  sessionStorage.removeItem(STORAGE_KEY);
}

export function hasOperatorKey(): boolean {
  return Boolean(getOperatorKey());
}
