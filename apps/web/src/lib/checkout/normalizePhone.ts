export function normalizePhone(value: string): string {
  const digits = value.replace(/\D/g, "");

  if (digits.startsWith("234") && digits.length === 13) {
    return `0${digits.slice(3)}`;
  }

  if (digits.startsWith("234") && digits.length === 12) {
    return `0${digits.slice(3)}`;
  }

  return digits;
}

export function isValidNigerianPhone(value: string): boolean {
  const normalized = normalizePhone(value);
  return /^0[789][01]\d{8}$/.test(normalized);
}

export function maskPhone(value: string): string {
  const normalized = normalizePhone(value);
  if (normalized.length !== 11) return value;
  return `${normalized.slice(0, 4)} XXX ${normalized.slice(7)}`;
}
