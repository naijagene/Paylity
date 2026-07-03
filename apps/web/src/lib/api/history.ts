import { apiRequest } from "./client";

export type TransactionHistoryItem = {
  reference: string;
  product_type: string;
  product_label: string;
  customer_phone: string;
  payable_amount: number;
  status: string;
  status_group: string;
  failure_reason?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type TransactionHistoryFilters = {
  phone: string;
  status_group?: string;
  product_type?: string;
  date_from?: string;
  date_to?: string;
  per_page?: number;
};

export async function fetchTransactionHistory(
  filters: TransactionHistoryFilters,
) {
  const query = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== "") {
      query.set(key, String(value));
    }
  });

  const { data } = await apiRequest<TransactionHistoryItem[]>(
    `/transactions?${query.toString()}`,
  );

  return { items: data };
}
