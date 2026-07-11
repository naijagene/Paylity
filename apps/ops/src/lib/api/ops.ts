import { ApiError, ApiOfflineError } from "@/lib/api/client";
import type { OpsDashboardSnapshot } from "@/lib/utils/dashboard";
import { isOperatorAuthError } from "@/lib/ops/operatorAuth";
import { handleOperatorAuthFailure } from "@/lib/ops/operatorAuth";
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
        const apiError = new ApiError("Unexpected API response.", {}, response.status);

        if (isOperatorAuthError(apiError)) {
          handleOperatorAuthFailure();
        }

        throw apiError;
      }

      throw new ApiOfflineError();
    }

    if (!body.success) {
      const apiError = new ApiError(
        body.message || "Request failed.",
        body.errors ?? {},
        response.status,
      );

      if (isOperatorAuthError(apiError)) {
        handleOperatorAuthFailure();
      }

      throw apiError;
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
  otp_required?: boolean;
  otp_verified?: boolean;
  created_at?: string | null;
  updated_at?: string | null;
  fulfilled_at?: string | null;
  timeline?: Array<{
    event_type: string;
    actor: string;
    summary: string;
    metadata?: Record<string, unknown> | null;
    occurred_at?: string | null;
  }>;
  fulfillment_attempts?: Array<{
    attempt_number: number;
    provider: string;
    request_id?: string | null;
    outcome: string;
    duration_ms?: number | null;
    failure_reason?: string | null;
    actor: string;
    request_payload?: Record<string, unknown> | null;
    response_payload?: Record<string, unknown> | null;
    attempted_at?: string | null;
  }>;
  webhook_history?: Array<{
    provider: string;
    event_id: string;
    event_type: string;
    status: string;
    created_at?: string | null;
  }>;
  catalog?: {
    provider?: string | null;
    service_id?: string | null;
    variation_code?: string | null;
    plan_name?: string | null;
    provider_variation_name?: string | null;
    display_name?: string | null;
    is_visible?: boolean | null;
    display_override?: boolean | null;
    customer_category?: string | null;
    data_size_label?: string | null;
    validity_label?: string | null;
    catalog_validated?: boolean;
  };
  fulfillment_payload?: {
    service_id?: string | null;
    variation_code?: string | null;
    billers_code?: string | null;
    recipient_phone?: string | null;
    meter_number?: string | null;
    network?: string | null;
    disco?: string | null;
  };
};

export type OpsSummary = {
  total_transactions_today: number;
  successful_payments_today: number;
  fulfilled_today: number;
  failed_today: number;
  pending_fulfillment: number;
  total_convenience_fees_today: number;
  revenue_today?: number;
};

export type OpsMonitoringSummary = {
  revenue: number;
  transactions: number;
  failures: number;
  pending: number;
  average_fulfillment_seconds: number | null;
  date_from: string;
  date_to: string;
  queue?: {
    connection: string;
    pending_jobs: number;
    failed_jobs: number;
    status: string;
  };
  otp?: {
    enabled: boolean;
    pending: number;
    verified_today: number;
    failed_today: number;
  };
};

export type OpsDailyReconciliation = {
  date: string;
  total_transactions: number;
  successful_payments: number;
  payment_failed: number;
  fulfillment_failed: number;
  fulfilled: number;
  pending_fulfillment: number;
  gross_revenue: number;
  product_value: number;
  convenience_fees: number;
  gateway_fees: number;
  success_rate: number;
};

export type OpsFailedTransaction = {
  reference: string;
  product_type: string;
  customer_phone: string;
  product_amount: number;
  payable_amount: number;
  status: string;
  failure_reason?: string | null;
  payment_reference?: string | null;
  created_at?: string | null;
};

export type OpsSettlementSummary = {
  date_from: string;
  date_to: string;
  transactions: number;
  collected_amount: number;
  product_value: number;
  convenience_fees: number;
  gateway_fees: number;
  estimated_net: number;
};

export type OpsRetrySummary = {
  date_from: string;
  date_to: string;
  total_retries: number;
  successful_retries: number;
  failed_retries: number;
  items: Array<{
    transaction_reference?: string | null;
    product_type?: string | null;
    customer_phone?: string | null;
    transaction_status?: string | null;
    attempt_number: number;
    provider: string;
    outcome: string;
    duration_ms?: number | null;
    failure_reason?: string | null;
    actor: string;
    attempted_at?: string | null;
  }>;
};

export type OpsNote = {
  id: number;
  body: string;
  author: string;
  created_at?: string | null;
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

export function getReceiptDownloadUrl(reference: string): string {
  return `${getOpsApiBaseUrl()}/transactions/${encodeURIComponent(reference)}/receipt/download`;
}

export async function fetchOpsDashboard(): Promise<OpsDashboardSnapshot> {
  const { data } = await opsRequest<OpsDashboardSnapshot>("/ops/dashboard");

  return data;
}

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

export async function retryOpsFulfillment(reference: string) {
  const { data, message } = await opsRequest<Record<string, unknown>>(
    `/ops/transactions/${encodeURIComponent(reference)}/retry-fulfillment`,
    { method: "POST" },
  );

  return { data, message };
}

export async function fetchOpsMonitoring(params?: {
  date_from?: string;
  date_to?: string;
}) {
  const query = new URLSearchParams();

  if (params?.date_from) {
    query.set("date_from", params.date_from);
  }

  if (params?.date_to) {
    query.set("date_to", params.date_to);
  }

  const suffix = query.toString() ? `?${query.toString()}` : "";
  const { data } = await opsRequest<OpsMonitoringSummary>(
    `/ops/monitoring${suffix}`,
  );

  return data;
}

export async function fetchOpsNotes(reference: string) {
  const { data } = await opsRequest<OpsNote[]>(
    `/ops/transactions/${encodeURIComponent(reference)}/notes`,
  );

  return data;
}

export async function createOpsNote(reference: string, body: string) {
  const { data, message } = await opsRequest<OpsNote>(
    `/ops/transactions/${encodeURIComponent(reference)}/notes`,
    {
      method: "POST",
      body: JSON.stringify({ body }),
    },
  );

  return { data, message };
}

export async function fetchDailyReconciliation(date?: string) {
  const query = date ? `?date=${encodeURIComponent(date)}` : "";
  const { data } = await opsRequest<OpsDailyReconciliation>(
    `/ops/reports/daily-reconciliation${query}`,
  );

  return data;
}

export async function fetchFailedTransactionsReport(params?: {
  date_from?: string;
  date_to?: string;
}) {
  const query = new URLSearchParams();

  if (params?.date_from) {
    query.set("date_from", params.date_from);
  }

  if (params?.date_to) {
    query.set("date_to", params.date_to);
  }

  const suffix = query.toString() ? `?${query.toString()}` : "";
  const { data } = await opsRequest<OpsFailedTransaction[]>(
    `/ops/reports/failed-transactions${suffix}`,
  );

  return data;
}

export async function fetchSettlementSummary(params?: {
  date_from?: string;
  date_to?: string;
}) {
  const query = new URLSearchParams();

  if (params?.date_from) {
    query.set("date_from", params.date_from);
  }

  if (params?.date_to) {
    query.set("date_to", params.date_to);
  }

  const suffix = query.toString() ? `?${query.toString()}` : "";
  const { data } = await opsRequest<OpsSettlementSummary>(
    `/ops/reports/settlement-summary${suffix}`,
  );

  return data;
}

export type OpsReconciliationItem = {
  reference: string;
  classification?: string | null;
  customer_phone?: string | null;
  customer_email?: string | null;
  product_type: string;
  amount: number;
  payment_state: string;
  fulfillment_state: string;
  payment_reference?: string | null;
  vtpass_request_id?: string | null;
  provider_response?: string | null;
  age_minutes?: number | null;
  retry_count?: number;
  next_retry_at?: string | null;
  manual_review_reason?: string | null;
  needs_manual_review?: boolean;
};

export type OpsReconciliationSnapshot = {
  summary: {
    paid_unfulfilled: number;
    stale_payment_pending: number;
    uncertain_provider_outcomes: number;
    retry_due: number;
    retry_exhausted: number;
    manual_review: number;
    amount_mismatch: number;
    repaired_today: number;
  };
  queues: {
    payment_exceptions: OpsReconciliationItem[];
    fulfillment_exceptions: OpsReconciliationItem[];
    provider_uncertainty: OpsReconciliationItem[];
    manual_review: OpsReconciliationItem[];
    dead_letters: OpsReconciliationItem[];
  };
  config: Record<string, number>;
};

export async function fetchOpsReconciliation() {
  const { data } = await opsRequest<OpsReconciliationSnapshot>("/ops/reconciliation");
  return data;
}

export async function opsReconcilePayment(reference: string) {
  return opsRequest(`/ops/reconciliation/${encodeURIComponent(reference)}/reconcile-payment`, {
    method: "POST",
  });
}

export async function opsReconcileFulfillment(reference: string) {
  return opsRequest(`/ops/reconciliation/${encodeURIComponent(reference)}/reconcile-fulfillment`, {
    method: "POST",
  });
}

export async function opsRetryReconciliation(reference: string) {
  return opsRequest(`/ops/reconciliation/${encodeURIComponent(reference)}/retry`, {
    method: "POST",
  });
}

export async function opsResumeAutomation(reference: string) {
  return opsRequest(`/ops/reconciliation/${encodeURIComponent(reference)}/resume-automation`, {
    method: "POST",
  });
}

export async function fetchRetrySummary(params?: {
  date_from?: string;
  date_to?: string;
}) {
  const query = new URLSearchParams();

  if (params?.date_from) {
    query.set("date_from", params.date_from);
  }

  if (params?.date_to) {
    query.set("date_to", params.date_to);
  }

  const suffix = query.toString() ? `?${query.toString()}` : "";
  const { data } = await opsRequest<OpsRetrySummary>(
    `/ops/reports/retry-summary${suffix}`,
  );

  return data;
}
