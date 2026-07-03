import { apiRequest } from "./client";

export type TransactionDetail = {
  reference: string;
  product_type: string;
  customer_phone: string;
  customer_email?: string | null;
  customer_name?: string | null;
  product_amount: number;
  convenience_fee: number;
  gateway_fee: number;
  payable_amount: number;
  currency: string;
  status: string;
  payment_provider?: string | null;
  payment_reference?: string | null;
  payment_authorization_url?: string | null;
  fulfillment_provider?: string | null;
  fulfillment_reference?: string | null;
  failure_reason?: string | null;
  fulfillment_details?: Record<string, string | number> | null;
  verified_phone?: boolean;
  created_at?: string | null;
  updated_at?: string | null;
};

export async function getTransaction(
  reference: string,
): Promise<TransactionDetail> {
  const { data } = await apiRequest<TransactionDetail>(
    `/transactions/${encodeURIComponent(reference)}`,
  );

  return data;
}
