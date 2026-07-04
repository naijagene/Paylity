import { apiRequest } from "./client";
import type { TransactionReceipt } from "./transactions";

export type ReceiptPayload = TransactionReceipt;

export async function getReceipt(reference: string): Promise<ReceiptPayload> {
  const { data } = await apiRequest<ReceiptPayload>(
    `/transactions/${encodeURIComponent(reference)}/receipt`,
  );

  return data;
}

export function getReceiptDownloadUrl(reference: string): string {
  const baseUrl =
    process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000/api/v1";

  return `${baseUrl}/transactions/${encodeURIComponent(reference)}/receipt/download`;
}

export async function downloadReceipt(reference: string): Promise<void> {
  const url = getReceiptDownloadUrl(reference);
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
