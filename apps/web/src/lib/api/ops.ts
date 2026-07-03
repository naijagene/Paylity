import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { getOperatorKey } from "@/lib/ops/operatorKey";

export type ApiSuccessResponse<T> = {
  success: true;
  message: string;
  data: T;
  meta?: Record<string, unknown>;
};

export type ApiErrorResponse = {
  success: false;
  message: string;
  errors?: Record<string, unknown>;
};

const DEFAULT_OPS_API_BASE_URL = "http://127.0.0.1:8000/api/v1";

export function getOpsApiBaseUrl(): string {
  return (
    process.env.NEXT_PUBLIC_OPERATOR_API_BASE_URL ??
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    DEFAULT_OPS_API_BASE_URL
  );
}

function isNetworkFailure(error: unknown): boolean {
  return (
    error instanceof TypeError ||
    (error instanceof Error &&
      (error.message.includes("Failed to fetch") ||
        error.message.includes("NetworkError") ||
        error.message.includes("fetch")))
  );
}

export async function opsRequest<T>(
  path: string,
  options: RequestInit = {},
): Promise<{ data: T; message: string; meta?: Record<string, unknown> }> {
  const operatorKey = getOperatorKey();

  if (!operatorKey) {
    throw new ApiError(
      "Operator access key is required.",
      { code: "OPERATOR_KEY_MISSING" },
      401,
    );
  }

  const url = `${getOpsApiBaseUrl()}${path}`;

  try {
    const response = await fetch(url, {
      ...options,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        "X-Operator-Key": operatorKey,
        ...options.headers,
      },
    });

    let body: ApiSuccessResponse<T> | ApiErrorResponse;

    try {
      body = (await response.json()) as ApiSuccessResponse<T> | ApiErrorResponse;
    } catch {
      if (!response.ok) {
        throw new ApiError("Unexpected API response.", {}, response.status);
      }

      throw new ApiOfflineError();
    }

    if (!body.success) {
      throw new ApiError(
        body.message || "Request failed.",
        body.errors ?? {},
        response.status,
      );
    }

    return {
      data: body.data,
      message: body.message,
      meta: body.meta,
    };
  } catch (error) {
    if (error instanceof ApiError || error instanceof ApiOfflineError) {
      throw error;
    }

    if (isNetworkFailure(error)) {
      throw new ApiOfflineError();
    }

    throw error;
  }
}

export type OpsTransactionListItem = {
  reference: string;
  product_type: string;
  customer_phone: string;
  product_amount: number;
  payable_amount: number;
  status: string;
  failure_reason?: string | null;
  created_at?: string | null;
};

export type OpsTransactionDetail = {
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
  auto_fulfill_attempted?: boolean | null;
  auto_fulfill_outcome?: string | null;
  auto_fulfill_reason?: string | null;
  request_payload?: Record<string, unknown> | null;
  response_payload?: Record<string, unknown> | null;
  ip_address?: string | null;
  user_agent?: string | null;
  verified_phone?: boolean;
  created_at?: string | null;
  updated_at?: string | null;
};

export type OpsSummary = {
  total_transactions_today: number;
  successful_payments_today: number;
  fulfilled_today: number;
  failed_today: number;
  pending_fulfillment: number;
  total_convenience_fees_today: number;
};

export type OpsSearchParams = {
  reference?: string;
  phone?: string;
  status?: string;
  product_type?: string;
  date_from?: string;
  date_to?: string;
  per_page?: number;
};

export async function fetchOpsSummary(): Promise<OpsSummary> {
  const { data } = await opsRequest<OpsSummary>("/ops/summary");
  return data;
}

export async function searchOpsTransactions(params: OpsSearchParams = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== "") {
      query.set(key, String(value));
    }
  });

  const suffix = query.toString() ? `?${query.toString()}` : "";
  const { data, meta } = await opsRequest<OpsTransactionListItem[]>(
    `/ops/transactions${suffix}`,
  );

  return { items: data, meta };
}

export async function fetchOpsTransaction(
  reference: string,
): Promise<OpsTransactionDetail> {
  const { data } = await opsRequest<OpsTransactionDetail>(
    `/ops/transactions/${encodeURIComponent(reference)}`,
  );

  return data;
}

export async function fulfillOpsTransaction(reference: string) {
  const { data, message } = await opsRequest<Record<string, unknown>>(
    `/ops/transactions/${encodeURIComponent(reference)}/fulfill`,
    { method: "POST" },
  );

  return { data, message };
}
