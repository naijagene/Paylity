import { apiRequest } from "./client";
import type { TransactionReceipt } from "./transactions";

export type VerifyPaystackPaymentResponse = {
  reference: string;
  status: string;
  payment_status: string;
  product_type: string;
  product_amount: number;
  convenience_fee: number;
  gateway_fee: number;
  payable_amount: number;
  currency: string;
  verified_at: string | null;
  fulfillment_status: string;
  failure_reason?: string;
  receipt?: TransactionReceipt | null;
};

export async function verifyPaystackPayment(
  reference: string,
): Promise<VerifyPaystackPaymentResponse> {
  const { data } = await apiRequest<VerifyPaystackPaymentResponse>(
    `/payments/paystack/verify/${encodeURIComponent(reference)}`,
  );

  return data;
}
