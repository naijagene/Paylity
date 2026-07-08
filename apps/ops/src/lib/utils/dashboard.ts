export type OpsDashboardExecutive = {
  revenue_today: number;
  transactions_today: number;
  success_rate: number;
  pending: number;
  failed: number;
  average_transaction: number;
  average_fulfillment_seconds: number | null;
  queue_size: number;
  api_health: string;
};

export type OpsRevenuePeriod = {
  total_revenue: number;
  platform_fees: number;
  gateway_charges: number;
  net_revenue: number;
  transactions: number;
};

export type OpsProductAnalytics = {
  count: number;
  revenue: number;
  percentage: number;
};

export type OpsDashboardAlert = {
  severity: "critical" | "warning" | "info";
  code: string;
  message: string;
};

export type OpsDashboardSnapshot = {
  enabled: boolean;
  refreshed_at: string;
  executive: OpsDashboardExecutive;
  revenue: {
    today: OpsRevenuePeriod;
    yesterday: OpsRevenuePeriod;
    week: OpsRevenuePeriod;
    month: OpsRevenuePeriod;
  };
  transactions: {
    airtime: OpsProductAnalytics;
    data: OpsProductAnalytics;
    electricity: OpsProductAnalytics;
    total: number;
  };
  providers: Record<
    string,
    {
      status: string;
      enabled?: boolean;
      pending_jobs?: number;
      failed_jobs?: number;
    }
  >;
  fraud: {
    otp_enabled: boolean;
    otp_failures_today: number;
    otp_pending: number;
    failed_verifications: number;
    blocked_transactions: number;
    daily_limit_hits: number;
  };
  alerts: OpsDashboardAlert[];
  platform: {
    checkout_enabled: boolean;
    maintenance_mode: boolean;
    incident_mode: boolean;
    message: string | null;
  };
  vtpass?: OpsVtpassOperations;
  reliability?: OpsReliabilitySnapshot;
};

export type OpsReliabilitySnapshot = {
  webhooks: {
    processed_24h: number;
    failed_24h: number;
    pending: number;
    duplicate_24h: number;
  };
  reconciliation: {
    stale_payment_pending: number;
    stale_payment_success: number;
    stale_fulfillment_pending: number;
  };
  retry_queue: {
    due_now: number;
    scheduled: number;
    manual_review: number;
  };
  manual_review: {
    count: number;
    items: Array<Record<string, unknown>>;
  };
  retry_items: Array<Record<string, unknown>>;
  config: {
    payment_reconcile_stale_minutes: number;
    fulfillment_stale_minutes: number;
    fulfillment_retry_max_attempts: number;
    fulfillment_retry_intervals_minutes: number[];
  };
};

export type OpsVtpassProductReadiness = {
  service_enabled: boolean;
  provider_enabled: boolean;
  ready: boolean;
};

export type OpsVtpassOperations = {
  environment: string;
  base_url_host: string;
  status: string;
  enabled: boolean;
  auto_fulfill: boolean;
  live_safety_mode: boolean;
  live_test_max_amount: number;
  balance: {
    available: boolean;
    balance: number | null;
    currency: string;
    environment: string;
    message: string | null;
  };
  product_readiness: Record<string, OpsVtpassProductReadiness>;
};

export type LiveFeedItem = {
  reference: string;
  product_type: string;
  customer_phone: string;
  payable_amount: number;
  status: string;
  created_at?: string | null;
};

export function calculateAverageTransaction(
  revenue: number,
  successfulTransactions: number,
): number {
  if (successfulTransactions <= 0) {
    return 0;
  }

  return Math.round(revenue / successfulTransactions);
}

export function sortLiveFeedNewestFirst<T extends { created_at?: string | null }>(
  items: T[],
): T[] {
  return [...items].sort((left, right) => {
    const leftTime = left.created_at ? Date.parse(left.created_at) : 0;
    const rightTime = right.created_at ? Date.parse(right.created_at) : 0;

    return rightTime - leftTime;
  });
}

export function aggregateRevenueTotals(
  periods: OpsDashboardSnapshot["revenue"],
): {
  totalRevenue: number;
  platformFees: number;
  gatewayCharges: number;
  netRevenue: number;
} {
  const today = periods.today;

  return {
    totalRevenue: today.total_revenue,
    platformFees: today.platform_fees,
    gatewayCharges: today.gateway_charges,
    netRevenue: today.net_revenue,
  };
}

export function buildProductChartData(
  transactions: OpsDashboardSnapshot["transactions"],
): Array<{ label: string; value: number; percentage: number }> {
  return [
    {
      label: "Airtime",
      value: transactions.airtime.count,
      percentage: transactions.airtime.percentage,
    },
    {
      label: "Data",
      value: transactions.data.count,
      percentage: transactions.data.percentage,
    },
    {
      label: "Electricity",
      value: transactions.electricity.count,
      percentage: transactions.electricity.percentage,
    },
  ];
}

export function buildRevenueChartData(
  revenue: OpsDashboardSnapshot["revenue"],
): Array<{ label: string; value: number }> {
  return [
    { label: "Today", value: revenue.today.total_revenue },
    { label: "Yesterday", value: revenue.yesterday.total_revenue },
    { label: "Week", value: revenue.week.total_revenue },
    { label: "Month", value: revenue.month.total_revenue },
  ];
}

export function formatVtpassEnvironment(environment: string): string {
  return environment === "production" ? "Production" : "Sandbox";
}

export function formatVtpassBalance(
  balance: OpsVtpassOperations["balance"] | undefined,
): string {
  if (!balance?.available || balance.balance === null) {
    return balance?.message ?? "Unavailable";
  }

  return `₦${balance.balance.toLocaleString("en-NG", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export function productIcon(productType: string): string {
  switch (productType) {
    case "airtime":
      return "📱";
    case "data":
      return "📶";
    case "electricity":
      return "⚡";
    default:
      return "🧾";
  }
}

export function formatRelativeTimestamp(value?: string | null): string {
  if (!value) {
    return "—";
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return "—";
  }

  return date.toLocaleTimeString("en-NG", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
}
