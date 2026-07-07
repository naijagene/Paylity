import { getOpsApiBaseUrl } from "@/lib/api/ops";

export async function downloadReceipt(reference: string): Promise<void> {
  const url = `${getOpsApiBaseUrl()}/transactions/${encodeURIComponent(reference)}/receipt/download`;
  const response = await fetch(url);

  if (!response.ok) {
    throw new Error("Unable to download receipt.");
  }

  const blob = await response.blob();
  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = objectUrl;
  anchor.download = `paylity-receipt-${reference}.html`;
  anchor.click();
  URL.revokeObjectURL(objectUrl);
}
