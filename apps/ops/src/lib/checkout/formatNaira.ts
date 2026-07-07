export function formatNaira(amount: number): string {
  return `₦${amount.toLocaleString("en-NG")}`;
}
