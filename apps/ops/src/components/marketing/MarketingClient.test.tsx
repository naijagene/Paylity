import { fireEvent, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { MarketingClient } from "@/components/marketing/MarketingClient";
import { renderWithProviders } from "@/test/renderWithProviders";

const mockSnapshot = {
  refreshed_at: "2026-07-15T12:00:00Z",
  kpis: {
    generated: 12,
    unused: 4,
    reserved: 2,
    redeemed: 6,
    remaining: 4,
    expired: 1,
    active: 8,
    blocked_attempts: 3,
    review_rate_pct: 40,
    share_rate_pct: 20,
    total_campaigns: 4,
    active_campaigns: 3,
    expired_campaigns: 1,
    shared_campaigns: 1,
    unique_campaigns: 3,
    generated_codes: 12,
    successful_redemptions: 6,
    remaining_capacity: 4,
    expired_reservations: 1,
  },
  reviews: { count: 2, average_rating: 4.5, distribution: { 5: 2 } },
  campaigns: [
    {
      id: 1,
      name: "Airtime Launch Promo",
      amount: 1000,
      distribution_mode: "shared_code" as const,
      generated_count: 1,
      max_redemptions: 2,
      redeemed_count: 1,
      unused_count: 0,
      reserved_count: 1,
      released_count: 0,
      expired_reservations: 0,
      remaining_capacity: 0,
      active: true,
      one_per_phone: true,
      one_per_email: true,
      one_per_device: true,
    },
  ],
  vouchers: [
    {
      id: 10,
      name: "Airtime Launch Promo",
      code: "PYL-TEST-CODE1",
      amount: 1000,
      max_redemptions: 2,
      redeemed_count: 1,
      remaining_redemptions: 1,
      active: true,
      status: "reserved",
    },
  ],
};

vi.mock("@/lib/hooks/usePolling", () => ({
  usePolling: () => ({
    data: mockSnapshot,
    loading: false,
    error: null,
    refresh: vi.fn(),
  }),
}));

vi.mock("@/lib/api/ops", async () => {
  const actual = await vi.importActual<typeof import("@/lib/api/ops")>("@/lib/api/ops");
  return {
    ...actual,
    fetchOpsVoucherRedemptions: vi.fn(async () => ({
      data: [
        {
          id: 1,
          voucher_code: "PYL-TEST-CODE1",
          campaign_name: "Airtime Launch Promo",
          customer_phone: "08031234567",
          reference: "PYL-20260715-TEST01",
          status: "reserved",
          discount_amount: 1000,
          reserved_at: "2026-07-15T12:00:00Z",
          redeemed_at: null,
        },
      ],
      meta: { total: 1 },
    })),
    fetchOpsVoucherAbuse: vi.fn(async () => ({
      window_days: 14,
      summary: {
        phone_blocked: 1,
        device_blocked: 0,
        email_blocked: 0,
        invalid_voucher: 0,
        expired_voucher: 0,
        capacity_exceeded: 1,
        reservation_expired: 0,
      },
      blocked_trend: [{ date: "2026-07-15", total: 1 }],
      recent_events: [],
    })),
    fetchOpsVoucherAnalytics: vi.fn(async () => ({
      daily_redemptions: [{ date: "2026-07-15", total: 2 }],
      campaign_usage: [{ id: 1, name: "Airtime Launch Promo", distribution_mode: "shared_code", redeemed_count: 1, capacity: 2 }],
      network_distribution: [{ label: "All", value: 1 }],
      blocked_trend: [{ date: "2026-07-15", total: 1 }],
    })),
    fetchOpsVoucherCustomerLookup: vi.fn(async () => ({
      query: "08031234567",
      redemptions: [],
      transactions: [],
    })),
    opsMarketingSetCampaignActive: vi.fn(async () => ({})),
    opsMarketingExportUsage: vi.fn(async () => undefined),
  };
});

describe("MarketingClient", () => {
  it("renders voucher operations dashboard overview kpis", () => {
    renderWithProviders(<MarketingClient />);

    expect(screen.getByRole("heading", { name: "Voucher Operations" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Dashboard KPIs" })).toBeInTheDocument();
    expect(screen.getByText("Total Campaigns")).toBeInTheDocument();
    expect(screen.getByText("Remaining Capacity")).toBeInTheDocument();
    expect(screen.getByText("Blocked Attempts")).toBeInTheDocument();
  });

  it("renders redemption log when the tab is selected", async () => {
    renderWithProviders(<MarketingClient />);

    fireEvent.click(screen.getByRole("button", { name: "Redemption Log" }));

    expect(screen.getByRole("heading", { name: "Redemption Log" })).toBeInTheDocument();
    expect(await screen.findByText("PYL-TEST-CODE1")).toBeInTheDocument();
    await waitFor(() => {
      expect(screen.getByText("PYL-20260715-TEST01")).toBeInTheDocument();
    });
  });

  it("renders abuse monitoring summary when the tab is selected", async () => {
    renderWithProviders(<MarketingClient />);

    fireEvent.click(screen.getByRole("button", { name: "Abuse Monitoring" }));

    expect(await screen.findByText("Phone blocked")).toBeInTheDocument();
    expect(screen.getByText("Capacity exceeded")).toBeInTheDocument();
  });
});
