const DEVICE_ID_KEY = "paylity.device_id";

export function getDeviceId(): string {
  if (typeof window === "undefined") {
    return "";
  }

  const existing = window.localStorage.getItem(DEVICE_ID_KEY);
  if (existing) {
    return existing;
  }

  const generated = crypto.randomUUID();
  window.localStorage.setItem(DEVICE_ID_KEY, generated);

  return generated;
}
