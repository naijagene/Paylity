import { describe, expect, it } from "vitest";
import {
  aggregateRevenueTotals,
  buildProductChartData,
  buildRevenueChartData,
  calculateAverageTransaction,
  sortLiveFeedNewestFirst,
} from "@/lib/utils/dashboard";

describe("dashboard calculations", () => {
  it("calculates average transaction from revenue and successful count", () => {
    expect(calculateAverageTransaction(15000, 3)).toBe(5000);
    expect(calculateAverageTransaction(0, 0)).toBe(0);
  });

  it("aggregates revenue totals from today period", () => {
    const totals = aggregateRevenueTotals({
      today: {
        total_revenue: 10000,
        platform_fees: 500,
        gateway_charges: 200,
        net_revenue: 9800,
        transactions: 4,
      },
      yesterday: {
        total_revenue: 0,
        platform_fees: 0,
        gateway_charges: 0,
        net_revenue: 0,
        transactions: 0,
      },
      week: {
        total_revenue: 10000,
        platform_fees: 500,
        gateway_charges: 200,
        net_revenue: 9800,
        transactions: 4,
      },
      month: {
        total_revenue: 10000,
        platform_fees: 500,
        gateway_charges: 200,
        net_revenue: 9800,
        transactions: 4,
      },
    });

    expect(totals).toEqual({
      totalRevenue: 10000,
      platformFees: 500,
      gatewayCharges: 200,
      netRevenue: 9800,
    });
  });
});

describe("live feed sorting", () => {
  it("sorts transactions newest first", () => {
    const sorted = sortLiveFeedNewestFirst([
      { reference: "A", created_at: "2026-07-08T10:00:00Z" },
      { reference: "B", created_at: "2026-07-08T12:00:00Z" },
      { reference: "C", created_at: "2026-07-08T11:00:00Z" },
    ]);

    expect(sorted.map((item) => item.reference)).toEqual(["B", "C", "A"]);
  });
});

describe("chart data builders", () => {
  it("builds product analytics chart data", () => {
    const chart = buildProductChartData({
      airtime: { count: 2, revenue: 2000, percentage: 40 },
      data: { count: 2, revenue: 3000, percentage: 40 },
      electricity: { count: 1, revenue: 1000, percentage: 20 },
      total: 5,
    });

    expect(chart).toEqual([
      { label: "Airtime", value: 2, percentage: 40 },
      { label: "Data", value: 2, percentage: 40 },
      { label: "Electricity", value: 1, percentage: 20 },
    ]);
  });

  it("builds revenue period chart data", () => {
    const chart = buildRevenueChartData({
      today: {
        total_revenue: 1000,
        platform_fees: 100,
        gateway_charges: 50,
        net_revenue: 950,
        transactions: 2,
      },
      yesterday: {
        total_revenue: 500,
        platform_fees: 50,
        gateway_charges: 25,
        net_revenue: 475,
        transactions: 1,
      },
      week: {
        total_revenue: 1500,
        platform_fees: 150,
        gateway_charges: 75,
        net_revenue: 1425,
        transactions: 3,
      },
      month: {
        total_revenue: 1500,
        platform_fees: 150,
        gateway_charges: 75,
        net_revenue: 1425,
        transactions: 3,
      },
    });

    expect(chart.map((item) => item.label)).toEqual([
      "Today",
      "Yesterday",
      "Week",
      "Month",
    ]);
  });
});
