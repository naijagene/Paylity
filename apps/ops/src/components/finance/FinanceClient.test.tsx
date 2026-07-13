import { screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { FinanceClient } from "@/components/finance/FinanceClient";
import { renderWithProviders } from "@/test/renderWithProviders";

vi.mock("@/lib/hooks/usePolling", () => ({
  usePolling: () => ({
    data: {
      refreshed_at: "2026-07-13T12:00:00Z",
      cards: {
        gross_collection_today_kobo: 110000,
        paylity_revenue_today_kobo: 10000,
        product_value_today_kobo: 100000,
        provider_cost_today_kobo: 95000,
        gateway_fees_today_kobo: 1500,
        gross_margin_today_kobo: 5000,
        paystack_clearing_kobo: 250000,
        settlement_difference_kobo: 0,
      },
      recent_postings: [
        {
          id: 1,
          reference: "PYL-20260713-TEST01",
          event_type: "payment_received",
          amount_kobo: 110000,
          status: "posted",
          posted_at: "2026-07-13T11:00:00Z",
          reversed: false,
        },
      ],
      daily_summaries: [
        {
          date: "2026-07-13",
          collections_kobo: 110000,
          revenue_kobo: 10000,
          provider_cost_kobo: 95000,
          gateway_fee_kobo: 1500,
          margin_kobo: 5000,
          difference_kobo: 0,
          close_status: "open",
        },
      ],
      settlement_exceptions: [],
      alerts: [
        {
          code: "SETTLEMENT_DIFFERENCE",
          severity: "warning",
          message: "Settlement difference is non-zero.",
        },
      ],
    },
    loading: false,
    error: null,
    refresh: vi.fn(),
  }),
}));

describe("FinanceClient", () => {
  it("renders finance center sections wired to the finance snapshot", () => {
    renderWithProviders(<FinanceClient />);

    expect(screen.getByRole("heading", { name: "Finance Center" })).toBeInTheDocument();
    expect(screen.getByText("Financial KPIs")).toBeInTheDocument();
    expect(screen.getByText("Ledger Summary")).toBeInTheDocument();
    expect(screen.getByText("Financial Alerts")).toBeInTheDocument();
    expect(screen.getByText("Settlement Summary")).toBeInTheDocument();
    expect(screen.getByText("Daily Close")).toBeInTheDocument();
    expect(screen.getByText("Recent Ledger Postings")).toBeInTheDocument();
    expect(screen.getByText("Revenue Today")).toBeInTheDocument();
    expect(screen.getByText("Gateway Fees Today")).toBeInTheDocument();
    expect(screen.getByText("Provider Cost Today")).toBeInTheDocument();
    expect(screen.getByText("Gross Margin Today")).toBeInTheDocument();
    expect(screen.getByText("PYL-20260713-TEST01")).toBeInTheDocument();
    expect(screen.getByText(/SETTLEMENT_DIFFERENCE/)).toBeInTheDocument();
  });
});
