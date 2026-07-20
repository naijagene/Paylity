import { fireEvent, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { CampaignDetailClient } from "@/components/marketing/CampaignDetailClient";
import { MarketingClient } from "@/components/marketing/MarketingClient";
import { ApiError } from "@/lib/api/client";
import { renderWithProviders } from "@/test/renderWithProviders";

const downloadVoucherCsvMock = vi.fn();

vi.mock("@/lib/hooks/usePolling", () => ({
  usePolling: () => ({
    data: {
      refreshed_at: "2026-07-20T12:00:00Z",
      kpis: {
        generated: 1,
        unused: 0,
        reserved: 0,
        redeemed: 1,
        remaining: 0,
        expired: 0,
        active: 1,
        blocked_attempts: 0,
        review_rate_pct: 0,
        share_rate_pct: 0,
        total_campaigns: 1,
        active_campaigns: 1,
        expired_campaigns: 0,
        shared_campaigns: 1,
        unique_campaigns: 0,
        generated_codes: 1,
        successful_redemptions: 1,
        remaining_capacity: 0,
        expired_reservations: 0,
      },
      reviews: { count: 0, average_rating: null, distribution: {} },
      campaigns: [
        {
          id: 4,
          name: "Airtime Launch Promo",
          amount: 1000,
          distribution_mode: "shared_code",
          generated_count: 1,
          max_redemptions: 2,
          redeemed_count: 1,
          remaining_capacity: 0,
          active: true,
          one_per_phone: true,
          one_per_email: true,
          one_per_device: true,
        },
      ],
      vouchers: [],
    },
    loading: false,
    error: null,
    refresh: vi.fn(),
  }),
}));

vi.mock("@/lib/api/ops", async () => {
  const actual = await vi.importActual<typeof import("@/lib/api/ops")>("@/lib/api/ops");
  return {
    ...actual,
    downloadVoucherCsv: (...args: Parameters<typeof downloadVoucherCsvMock>) => downloadVoucherCsvMock(...args),
    fetchOpsVoucherRedemptions: vi.fn(async () => ({ data: [], meta: { total: 0 } })),
    fetchOpsVoucherAbuse: vi.fn(async () => ({
      window_days: 14,
      summary: {
        phone_blocked: 0,
        device_blocked: 0,
        email_blocked: 0,
        invalid_voucher: 0,
        expired_voucher: 0,
        capacity_exceeded: 0,
        reservation_expired: 0,
      },
      blocked_trend: [],
      recent_events: [],
    })),
    fetchOpsVoucherAnalytics: vi.fn(async () => ({
      daily_redemptions: [],
      campaign_usage: [],
      network_distribution: [],
      blocked_trend: [],
    })),
    fetchOpsVoucherCustomerLookup: vi.fn(async () => ({ query: "", redemptions: [], transactions: [] })),
    fetchOpsVoucherCampaignDetail: vi.fn(async () => ({
      campaign: {
        id: 4,
        name: "Airtime Launch Promo",
        amount: 1000,
        distribution_mode: "shared_code",
        generated_count: 1,
        max_redemptions: 2,
        redeemed_count: 1,
        remaining_capacity: 0,
        active: true,
        one_per_phone: true,
        one_per_email: true,
        one_per_device: true,
      },
      capacity: {},
      statistics: {
        reserved: 0,
        redeemed: 1,
        released: 0,
        expired: 0,
        cancelled: 0,
        used_capacity: 1,
        total_capacity: 2,
        progress_pct: 50,
      },
      restrictions: {
        one_per_phone: true,
        one_per_email: true,
        one_per_device: true,
        reservation_timeout_minutes: 30,
      },
      vouchers: [],
    })),
    opsMarketingSetCampaignActive: vi.fn(async () => ({})),
    opsMarketingExportUsage: vi.fn(async () => undefined),
  };
});

describe("voucher CSV export buttons", () => {
  beforeEach(() => {
    downloadVoucherCsvMock.mockReset();
    downloadVoucherCsvMock.mockResolvedValue(undefined);
  });

  it("campaign table CSV button calls authenticated download", async () => {
    renderWithProviders(<MarketingClient />);

    fireEvent.click(screen.getByRole("button", { name: "Campaigns" }));
    fireEvent.click(screen.getByRole("button", { name: "Export CSV" }));

    await waitFor(() => {
      expect(downloadVoucherCsvMock).toHaveBeenCalledWith(4);
    });
  });

  it("campaign detail CSV button calls authenticated download", async () => {
    renderWithProviders(<CampaignDetailClient campaignId={4} />);

    fireEvent.click(await screen.findByRole("button", { name: "Export CSV" }));

    await waitFor(() => {
      expect(downloadVoucherCsvMock).toHaveBeenCalledWith(4);
    });
  });

  it("shows readable API error when CSV download fails", async () => {
    downloadVoucherCsvMock.mockRejectedValue(
      new ApiError("Invalid or missing operator access key.", { code: "OPERATOR_ACCESS_DENIED" }, 401),
    );

    renderWithProviders(<CampaignDetailClient campaignId={4} />);

    fireEvent.click(await screen.findByRole("button", { name: "Export CSV" }));

    expect(await screen.findByText("Invalid or missing operator access key.")).toBeInTheDocument();
  });
});
