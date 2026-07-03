import { apiRequest } from "./client";

export type ReceiptVerificationResult = {
  authentic: boolean;
  reference: string;
  product_type: string;
  product_label: string;
  customer_phone_masked: string;
  payable_amount: number;
  currency: string;
  status: string;
  payment_status: string;
  fulfillment_status: string;
  fulfillment_reference?: string | null;
  timestamp?: string | null;
  verified_at?: string | null;
};

export async function verifyReceipt(
  token: string,
): Promise<ReceiptVerificationResult> {
  const { data } = await apiRequest<ReceiptVerificationResult>(
    `/receipts/verify/${encodeURIComponent(token)}`,
  );

  return data;
}
