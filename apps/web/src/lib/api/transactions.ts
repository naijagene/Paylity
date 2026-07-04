import { apiRequest } from "./client";

export type TransactionEvent = {
  event_type: string;
  actor: string;
  summary: string;
  metadata?: Record<string, unknown> | null;
  occurred_at?: string | null;
};

export type TransactionReceipt = {
  brand: string;
  reference: string;
  product_type: string;
  product_label: string;
  product_display_name?: string;
  customer_phone?: string | null;
  customer_phone_masked: string;
  recipient_phone?: string | null;
  recipient_phone_masked?: string;
  phone_display?: string;
  customer_email?: string | null;
  product_amount: number;
  convenience_fee: number;
  gateway_fee: number;
  payable_amount: number;
  currency: string;
  status: string;
  payment_status: string;
  fulfillment_status: string;
  failure_reason?: string | null;
  fulfillment_reference?: string | null;
  timestamp?: string | null;
  timestamp_display?: string | null;
  verification_token?: string;
  verification_url?: string;
};

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
  receipt?: TransactionReceipt | null;
  timeline?: TransactionEvent[];
  is_terminal?: boolean;
  poll_interval_seconds?: number;
};

export async function getTransaction(
  reference: string,
): Promise<TransactionDetail> {
  const { data } = await apiRequest<TransactionDetail>(
    `/transactions/${encodeURIComponent(reference)}`,
  );

  return data;
}
