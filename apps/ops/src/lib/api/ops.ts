import { ApiError, ApiOfflineError, resolveApiErrorMessage } from "@/lib/api/client";
import type { OpsDashboardSnapshot, OpsVtpassWalletBalance } from "@/lib/utils/dashboard";
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
      if (process.env.NODE_ENV === "development") {
        console.error("Ops API error response", body);
      }

      const apiError = new ApiError(
        resolveApiErrorMessage(body.message || "Request failed.", body.errors ?? {}),
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
  finance?: OpsTransactionFinance;
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
  wallet?: OpsVtpassWalletBalance;
  vtpass?: {
    status: string;
    enabled: boolean;
    environment: string;
    balance: OpsVtpassWalletBalance;
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
  wallet?: {
    date: string;
    opening_balance: number | null;
    closing_balance: number | null;
    lowest_balance: number | null;
    highest_balance: number | null;
    readings: number;
    last_checked_at: string | null;
    recharge_events: unknown[];
    recharge_events_available?: boolean;
    recharge_events_note?: string;
  };
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

export async function refreshVtpassWallet() {
  const { data } = await opsRequest<OpsVtpassWalletBalance>("/ops/vtpass/wallet/refresh", {
    method: "POST",
  });

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

export type OpsFinancePosting = {
  id: number;
  reference?: string | null;
  event_type: string;
  description: string;
  debit_account?: string | null;
  credit_account?: string | null;
  amount_kobo: number;
  status: string;
  posted_at?: string | null;
  reversed: boolean;
};

export type OpsFinanceSnapshot = {
  refreshed_at: string;
  cards: {
    gross_collection_today_kobo: number;
    paylity_revenue_today_kobo: number;
    product_value_today_kobo: number;
    provider_cost_today_kobo: number;
    gateway_fees_today_kobo: number;
    gross_margin_today_kobo: number;
    paystack_clearing_kobo: number;
    settlement_difference_kobo: number;
  };
  recent_postings: OpsFinancePosting[];
  daily_summaries: Array<{
    date: string;
    collections_kobo: number;
    revenue_kobo: number;
    provider_cost_kobo: number;
    gateway_fee_kobo: number;
    margin_kobo: number;
    difference_kobo: number;
    close_status: string;
  }>;
  settlement_exceptions: Array<{
    reference: string;
    provider: string;
    expected_kobo: number;
    actual_kobo: number;
    difference_kobo: number;
    age_days?: number | null;
    status: string;
    exception_count: number;
  }>;
  alerts: Array<{
    code: string;
    severity: string;
    message: string;
  }>;
};

export type OpsTransactionFinance = {
  summary: {
    customer_paid_kobo: number;
    product_amount_kobo: number;
    convenience_fee_kobo: number;
    gateway_fee_charged_kobo: number;
    gateway_fee: {
      expected_kobo?: number | null;
      actual_kobo?: number | null;
      charged_kobo: number;
    };
    provider_cost_kobo?: number | null;
    provider_cost_source?: string | null;
    provider_cost_status?: string | null;
    gross_margin_kobo?: number | null;
    settlement_status: string;
  };
  ledger_history: OpsFinancePosting[];
};

export async function fetchOpsFinance() {
  const { data } = await opsRequest<OpsFinanceSnapshot>("/ops/finance");
  return data;
}

export async function opsFinanceReconcileSettlements(dryRun = true) {
  return opsRequest(`/ops/finance/reconcile-settlements?dry_run=${dryRun ? "1" : "0"}`, {
    method: "POST",
  });
}

export async function opsFinanceBackfill(dryRun = true) {
  return opsRequest(`/ops/finance/backfill?dry_run=${dryRun ? "1" : "0"}`, {
    method: "POST",
  });
}

export async function opsFinanceClose(dryRun = true) {
  return opsRequest(`/ops/finance/close?dry_run=${dryRun ? "1" : "0"}`, {
    method: "POST",
  });
}

export type OpsGoLiveCheck = {
  name: string;
  status: string;
  message: string;
  severity: string;
};

export type OpsGoLiveBlocker = {
  code: string;
  message: string;
  severity: string;
};

export type OpsGoLiveChecklistItem = {
  key: string;
  label: string;
  completed: boolean;
  completed_at?: string | null;
};

export type OpsGoLiveScheduler = {
  status: string;
  last_run?: string | null;
  last_run_at?: string | null;
  seconds_since_last_run?: number | null;
  age_seconds?: number | null;
  next_expected_run?: string | null;
};

export type OpsGoLiveSnapshot = {
  refreshed_at: string;
  launch_status: {
    status: string;
    environment: string;
    environment_badge: { label: string; variant: "success" | "processing" | "failed" | "info" };
    version: string;
    build: string;
    last_preflight_at?: string | null;
    scheduler: OpsGoLiveScheduler;
    backup: { last_run_at?: string | null; last_verified_at?: string | null };
  };
  preflight: {
    status: string;
    summary: { pass: number; warn: number; fail: number };
    checks: OpsGoLiveCheck[];
  };
  blockers: OpsGoLiveBlocker[];
  checklist: {
    items: OpsGoLiveChecklistItem[];
    completed_count: number;
    total_count: number;
    progress_pct: number;
    ready_for_production: boolean;
  };
  timeline: {
    last_backup?: string | null;
    last_verify_backup?: string | null;
    last_pricing_audit?: string | null;
    last_preflight?: string | null;
    last_financial_close?: string | null;
    last_settlement?: string | null;
    last_scheduler_heartbeat?: string | null;
  };
  launch_mode: {
    mode: string;
    daily_usage: {
      transaction_count: number;
      gross_collection_naira: number;
      transaction_limit_daily: number;
      revenue_limit_daily: number;
      transaction_utilization_pct?: number | null;
      revenue_utilization_pct?: number | null;
    };
  };
  provider_mode: {
    paystack: {
      mode: string;
      callback_url: string;
      webhook_route: string;
      configuration_complete: boolean;
    };
    vtpass: { mode: string; configuration_complete: boolean };
  };
  security: { app_debug: boolean; https_app_url: boolean; cors_origins: string[] };
  finance: {
    negative_margin_count: number;
    paystack_clearing_kobo: number;
    settlement_difference_kobo: number;
  };
  pricing_audit_summary: { negative_margin_count: number; all_positive: boolean };
  payment_certification?: OpsPaymentCertificationSnapshot;
};

export type OpsPaymentCertificationRun = {
  id: number;
  reference?: string | null;
  environment: string;
  paystack_mode: string;
  provider_mode?: string | null;
  intended_product_type: string;
  intended_product_amount_kobo: number;
  expected_total_kobo: number;
  payment_status?: string | null;
  fulfillment_status?: string | null;
  ledger_status?: string | null;
  reconciliation_status?: string | null;
  settlement_expectation_status?: string | null;
  receipt_status?: string | null;
  result: string;
  started_at?: string | null;
  completed_at?: string | null;
  is_active?: boolean;
};

export type OpsPaymentCertificationSnapshot = {
  paystack_mode: string;
  vtpass_mode: string;
  environment: string;
  launch_mode: string;
  preflight_verdict: string;
  daily_usage: OpsGoLiveSnapshot["launch_mode"]["daily_usage"];
  active_run?: OpsPaymentCertificationRun | null;
  last_certified?: OpsPaymentCertificationRun | null;
};

export async function fetchOpsGoLive() {
  const { data } = await opsRequest<OpsGoLiveSnapshot>("/ops/go-live");
  return data;
}

export async function fetchOpsGoLiveHeartbeat() {
  const { data } = await opsRequest<OpsGoLiveScheduler>("/ops/go-live/heartbeat");
  return data;
}

export async function opsGoLivePreflight(strict = false) {
  return opsRequest(`/ops/go-live/preflight?strict=${strict ? "1" : "0"}`, { method: "POST" });
}

export async function opsGoLiveBackup() {
  return opsRequest("/ops/go-live/backup", { method: "POST" });
}

export async function opsGoLiveVerifyBackup() {
  return opsRequest("/ops/go-live/backup/verify", { method: "POST" });
}

export async function opsGoLivePricingAudit(product = "airtime") {
  return opsRequest(`/ops/go-live/pricing-audit?product=${product}`);
}

export async function opsGoLiveUpdateChecklist(items: Record<string, boolean>) {
  return opsRequest<OpsGoLiveSnapshot["checklist"]>("/ops/go-live/checklist", {
    method: "PATCH",
    body: JSON.stringify({ items }),
  });
}

export async function opsGoLiveSetMode(
  mode: "staging" | "soft_launch" | "live" | "maintenance",
  confirmProduction = false,
  confirmMaintenance = false,
) {
  return opsRequest("/ops/go-live/mode", {
    method: "POST",
    body: JSON.stringify({
      mode,
      confirm_production: confirmProduction,
      confirm_maintenance: confirmMaintenance,
    }),
  });
}

export async function opsPaymentCertificationPreflight(strict = false) {
  return opsRequest(`/ops/go-live/payment-certification/preflight?strict=${strict ? "1" : "0"}`, {
    method: "POST",
  });
}

export async function opsPaymentCertificationCreate(payload: {
  product?: string;
  amount?: number;
  phone?: string;
  network?: string;
  confirm_live_certification: boolean;
  force?: boolean;
}) {
  const { data } = await opsRequest<OpsPaymentCertificationRun>("/ops/go-live/payment-certification", {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return data;
}

export async function opsPaymentCertificationLinkReference(runId: number, reference: string) {
  const { data } = await opsRequest<OpsPaymentCertificationRun>(
    `/ops/go-live/payment-certification/${runId}/reference`,
    {
      method: "PATCH",
      body: JSON.stringify({ reference }),
    },
  );
  return data;
}

export async function opsPaymentCertificationRefresh(runId: number) {
  const { data } = await opsRequest<OpsPaymentCertificationRun>(
    `/ops/go-live/payment-certification/${runId}/refresh`,
    { method: "POST" },
  );
  return data;
}

export async function opsPaymentCertificationFinalize(runId: number, confirmFinalize = true) {
  const { data } = await opsRequest<OpsPaymentCertificationRun>(
    `/ops/go-live/payment-certification/${runId}/finalize`,
    {
      method: "POST",
      body: JSON.stringify({ confirm_finalize: confirmFinalize }),
    },
  );
  return data;
}

export async function opsPaymentCertificationExport(runId: number) {
  const { data } = await opsRequest<{
    filename: string;
    content_type: string;
    payload: Record<string, unknown>;
    sha256: string;
  }>(`/ops/go-live/payment-certification/${runId}/export`);
  return data;
}

export async function opsGoLiveExportJson() {
  const { data } = await opsRequest<Record<string, unknown>>("/ops/go-live/export/json");
  return data;
}

export function getOpsGoLiveExportPdfUrl(): string {
  return `${getOpsApiBaseUrl()}/ops/go-live/export/pdf`;
}

export type OpsMarketingCampaign = {
  id: number;
  name: string;
  amount: number;
  network?: string | null;
  distribution_mode: "unique_codes" | "shared_code";
  generated_count: number;
  max_redemptions?: number | null;
  redeemed_count: number;
  unused_count?: number;
  reserved_count?: number;
  released_count?: number;
  expired_reservations?: number;
  remaining_capacity?: number;
  expires_at?: string | null;
  active: boolean;
  one_per_phone: boolean;
  one_per_email: boolean;
  one_per_device: boolean;
  reservation_timeout_minutes?: number;
  shared_code?: boolean;
  shared_code_value?: string | null;
  shared_message?: string | null;
  created_by?: string | null;
  created_at?: string | null;
};

export type OpsMarketingSnapshot = {
  refreshed_at: string;
  kpis: {
    generated: number;
    unused?: number;
    reserved?: number;
    redeemed: number;
    remaining: number;
    expired: number;
    active: number;
    blocked_attempts?: number;
    review_rate_pct: number;
    share_rate_pct: number;
    total_campaigns?: number;
    active_campaigns?: number;
    expired_campaigns?: number;
    shared_campaigns?: number;
    unique_campaigns?: number;
    generated_codes?: number;
    successful_redemptions?: number;
    remaining_capacity?: number;
    expired_reservations?: number;
  };
  reviews: { count: number; average_rating: number | null; distribution: Record<number, number> };
  campaigns?: OpsMarketingCampaign[];
  vouchers: Array<{
    id: number;
    campaign_id?: number;
    campaign_name?: string;
    distribution_mode?: "unique_codes" | "shared_code";
    name: string;
    code: string;
    amount: number;
    max_redemptions: number;
    redeemed_count: number;
    remaining_redemptions: number;
    active: boolean;
    status?: string;
    immutable?: boolean;
    one_per_phone?: boolean;
    one_per_email?: boolean;
    one_per_device?: boolean;
  }>;
};

export async function fetchOpsMarketing(search?: string) {
  const query = search ? `?search=${encodeURIComponent(search)}` : "";
  const { data } = await opsRequest<OpsMarketingSnapshot>(`/ops/marketing/vouchers${query}`);
  return data;
}

export type OpsMarketingCreateCampaignPayload = {
  name: string;
  amount: 500 | 1000;
  distribution_mode: "unique_codes" | "shared_code";
  quantity?: number;
  max_redemptions?: number;
  network?: string | null;
  expires_at?: string | null;
  active?: boolean;
  one_per_phone?: boolean;
  one_per_email?: boolean;
  one_per_device?: boolean;
  reservation_timeout_minutes?: number;
};

export function formatExpiresAtForBackend(value: string | null | undefined): string | null {
  if (!value?.trim()) {
    return null;
  }

  const parsed = new Date(value);

  if (Number.isNaN(parsed.getTime())) {
    return null;
  }

  return parsed.toISOString();
}

export function buildOpsMarketingCampaignPayload(input: {
  name: string;
  amount: 500 | 1000;
  distributionMode: "unique_codes" | "shared_code";
  quantity?: number;
  maxRedemptions?: number;
  network?: string;
  expiresAt?: string;
  active?: boolean;
  onePerPhone?: boolean;
  onePerEmail?: boolean;
  onePerDevice?: boolean;
  reservationTimeoutMinutes?: number;
}): OpsMarketingCreateCampaignPayload {
  const payload: OpsMarketingCreateCampaignPayload = {
    name: input.name.trim(),
    amount: input.amount,
    distribution_mode: input.distributionMode,
    network: input.network?.trim() ? input.network.trim() : null,
    expires_at: formatExpiresAtForBackend(input.expiresAt),
    active: input.active ?? true,
    one_per_phone: input.onePerPhone ?? true,
    one_per_email: input.onePerEmail ?? true,
    one_per_device: input.onePerDevice ?? true,
    reservation_timeout_minutes: input.reservationTimeoutMinutes ?? 30,
  };

  if (input.distributionMode === "unique_codes") {
    payload.quantity = input.quantity ?? 1;
  } else {
    payload.max_redemptions = input.maxRedemptions;
  }

  return payload;
}

export async function opsMarketingCreateCampaign(payload: OpsMarketingCreateCampaignPayload) {
  if (process.env.NODE_ENV === "development") {
    console.log("Campaign Payload", payload);
  }

  try {
    const { data } = await opsRequest<{
      campaign: OpsMarketingCampaign;
      codes: string[];
      shared_message?: string | null;
    }>("/ops/marketing/campaigns", {
      method: "POST",
      body: JSON.stringify(payload),
    });

    return data;
  } catch (error) {
    if (process.env.NODE_ENV === "development" && error instanceof ApiError) {
      console.error("Campaign create failed", {
        payload,
        message: error.message,
        errors: error.errors,
        status: error.status,
      });
    }

    throw error;
  }
}

export async function opsMarketingSetVoucherActive(id: number, active: boolean) {
  return opsRequest(`/ops/marketing/vouchers/${id}/active`, {
    method: "POST",
    body: JSON.stringify({ active }),
  });
}

export async function opsMarketingSetCampaignActive(id: number, active: boolean) {
  return opsRequest(`/ops/marketing/campaigns/${id}/active`, {
    method: "POST",
    body: JSON.stringify({ active }),
  });
}

export async function opsMarketingExportUsage(campaignId?: number) {
  const query = campaignId ? `?campaign_id=${campaignId}` : "";
  const { data } = await opsRequest<Array<Record<string, unknown>>>(`/ops/marketing/vouchers/export${query}`);
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = campaignId ? `paylity-voucher-usage-${campaignId}.json` : "paylity-voucher-usage.json";
  anchor.click();
  URL.revokeObjectURL(url);
}

function parseContentDispositionFilename(header: string | null): string | null {
  if (!header) {
    return null;
  }

  const utf8Match = header.match(/filename\*=UTF-8''([^;]+)/i);
  if (utf8Match?.[1]) {
    return decodeURIComponent(utf8Match[1]);
  }

  const basicMatch = header.match(/filename="?([^";]+)"?/i);
  return basicMatch?.[1] ?? null;
}

export async function downloadOpsFile(path: string, fallbackFilename: string): Promise<void> {
  const operatorKey = getOperatorKey();

  if (!operatorKey) {
    throw new ApiError(
      "Operator access key is required.",
      { code: "OPERATOR_KEY_MISSING" },
      401,
    );
  }

  const url = `${getOpsApiBaseUrl()}${path}`;
  let objectUrl: string | null = null;
  let anchor: HTMLAnchorElement | null = null;

  try {
    const response = await fetch(url, {
      headers: {
        Accept: "text/csv, application/octet-stream, */*",
        "X-Operator-Key": operatorKey,
      },
    });

    if (!response.ok) {
      const contentType = response.headers.get("content-type") ?? "";

      if (contentType.includes("application/json")) {
        const body = (await response.json()) as ApiErrorResponse;
        const apiError = new ApiError(
          resolveApiErrorMessage(body.message || "Request failed.", body.errors ?? {}),
          body.errors ?? {},
          response.status,
        );

        if (isOperatorAuthError(apiError)) {
          handleOperatorAuthFailure();
        }

        throw apiError;
      }

      const apiError = new ApiError("Unable to download file.", {}, response.status);

      if (isOperatorAuthError(apiError)) {
        handleOperatorAuthFailure();
      }

      throw apiError;
    }

    const blob = await response.blob();
    const filename =
      parseContentDispositionFilename(response.headers.get("Content-Disposition")) ?? fallbackFilename;

    objectUrl = URL.createObjectURL(blob);
    anchor = document.createElement("a");
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.style.display = "none";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
  } catch (error) {
    if (error instanceof ApiError || error instanceof ApiOfflineError) {
      throw error;
    }

    if (isNetworkFailure(error)) {
      throw new ApiOfflineError();
    }

    throw error;
  } finally {
    if (anchor?.parentNode) {
      anchor.parentNode.removeChild(anchor);
    }

    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
    }
  }
}

export async function downloadVoucherCsv(campaignId?: number): Promise<void> {
  const query = campaignId ? `?campaign_id=${campaignId}` : "";
  const fallbackFilename = campaignId
    ? `paylity-voucher-usage-${campaignId}.csv`
    : "paylity-voucher-usage.csv";

  await downloadOpsFile(`/ops/marketing/vouchers/export.csv${query}`, fallbackFilename);
}

export type OpsVoucherRedemptionLogItem = {
  id: number;
  campaign_id?: number | null;
  campaign_name?: string | null;
  distribution_mode?: string | null;
  voucher_code?: string | null;
  voucher_name?: string | null;
  reference?: string | null;
  transaction_status?: string | null;
  status: string;
  discount_amount: number;
  customer_phone?: string | null;
  customer_phone_normalized?: string | null;
  customer_email?: string | null;
  device_id?: string | null;
  reserved_at?: string | null;
  redeemed_at?: string | null;
  released_at?: string | null;
  created_at?: string | null;
};

export type OpsVoucherAbuseSummary = {
  window_days: number;
  summary: {
    phone_blocked: number;
    device_blocked: number;
    email_blocked: number;
    invalid_voucher: number;
    expired_voucher: number;
    capacity_exceeded: number;
    reservation_expired: number;
  };
  blocked_trend: Array<{ date: string; total: number }>;
  recent_events: Array<{
    id: number;
    event_type: string;
    voucher_code?: string | null;
    reference?: string | null;
    metadata?: Record<string, unknown> | null;
    actor?: string | null;
    occurred_at?: string | null;
  }>;
};

export type OpsVoucherAnalytics = {
  daily_redemptions: Array<{ date: string; total: number }>;
  campaign_usage: Array<{
    id: number;
    name: string;
    distribution_mode: string;
    redeemed_count: number;
    capacity: number | null;
  }>;
  network_distribution: Array<{ label: string; value: number }>;
  blocked_trend: Array<{ date: string; total: number }>;
};

export type OpsVoucherCampaignDetail = {
  campaign: OpsMarketingCampaign;
  capacity: Record<string, number | null>;
  statistics: {
    reserved: number;
    redeemed: number;
    released: number;
    expired: number;
    cancelled: number;
    used_capacity: number;
    total_capacity: number;
    progress_pct: number;
  };
  restrictions: {
    one_per_phone: boolean;
    one_per_email: boolean;
    one_per_device: boolean;
    reservation_timeout_minutes: number;
  };
  vouchers: OpsMarketingSnapshot["vouchers"];
};

export type OpsVoucherCustomerLookup = {
  query: string;
  redemptions: OpsVoucherRedemptionLogItem[];
  transactions: Array<{
    reference: string;
    status: string;
    customer_phone: string;
    product_amount: number;
    payable_amount: number;
    voucher_code?: string | null;
    voucher_discount_amount?: number | null;
    created_at?: string | null;
  }>;
};

export async function fetchOpsVoucherRedemptions(params: {
  search?: string;
  status?: string;
  campaign_id?: number;
  sort_by?: string;
  sort_dir?: "asc" | "desc";
  per_page?: number;
  page?: number;
}) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== "") {
      query.set(key, String(value));
    }
  });
  const suffix = query.toString() ? `?${query.toString()}` : "";
  const { data, meta } = await opsRequest<OpsVoucherRedemptionLogItem[]>(`/ops/marketing/redemptions${suffix}`);
  return { data, meta };
}

export async function fetchOpsVoucherAbuse(days = 14) {
  const { data } = await opsRequest<OpsVoucherAbuseSummary>(`/ops/marketing/abuse?days=${days}`);
  return data;
}

export async function fetchOpsVoucherAnalytics() {
  const { data } = await opsRequest<OpsVoucherAnalytics>("/ops/marketing/analytics");
  return data;
}

export async function fetchOpsVoucherCampaignDetail(campaignId: number) {
  const { data } = await opsRequest<OpsVoucherCampaignDetail>(`/ops/marketing/campaigns/${campaignId}`);
  return data;
}

export async function fetchOpsVoucherCustomerLookup(query: string) {
  const { data } = await opsRequest<OpsVoucherCustomerLookup>(
    `/ops/marketing/customer-lookup?q=${encodeURIComponent(query)}`,
  );
  return data;
}

export async function opsMarketingExtendExpiry(campaignId: number, expiresAt: string) {
  const { data } = await opsRequest<OpsVoucherCampaignDetail>(`/ops/marketing/campaigns/${campaignId}/expiry`, {
    method: "PATCH",
    body: JSON.stringify({ expires_at: expiresAt }),
  });
  return data;
}

export async function opsMarketingIncreaseCapacity(campaignId: number, maxRedemptions: number) {
  const { data } = await opsRequest<OpsVoucherCampaignDetail>(`/ops/marketing/campaigns/${campaignId}/capacity`, {
    method: "PATCH",
    body: JSON.stringify({ max_redemptions: maxRedemptions }),
  });
  return data;
}
