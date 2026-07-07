export function maskPhone(phone?: string | null): string | null {
  if (!phone || phone.trim() === "") {
    return null;
  }

  const digits = phone.replace(/\D+/g, "");

  if (digits === "") {
    return null;
  }

  if (digits.length === 11 && digits.startsWith("0")) {
    return `${digits.slice(0, 4)} XXX ${digits.slice(7)}`;
  }

  if (digits.length >= 7) {
    return `${digits.slice(0, 4)} XXX ${digits.slice(-4)}`;
  }

  return phone;
}
