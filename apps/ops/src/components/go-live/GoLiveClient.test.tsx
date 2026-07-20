import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { GoLiveClient } from "@/components/go-live/GoLiveClient";

vi.mock("@/lib/hooks/usePolling", () => ({
  usePolling: () => ({
    data: {
      refreshed_at: "2026-07-14T12:00:00+01:00",
      launch_status: {
        status: "READY_WITH_WARNINGS",
        environment: "staging",
        environment_badge: { label: "Staging", variant: "info" },
        version: "1.0.0",
        build: "test-build",
        scheduler: {
          status: "healthy",
          last_run: "2026-07-14T11:59:00+01:00",
          seconds_since_last_run: 60,
          next_expected_run: "2026-07-14T12:00:00+01:00",
        },
        backup: {},
      },
      preflight: {
        status: "READY_WITH_WARNINGS",
        summary: { pass: 18, warn: 2, fail: 0 },
        checks: [
          {
            name: "Database",
            status: "PASS",
            message: "Database connection is healthy and writable.",
            severity: "info",
          },
        ],
      },
      blockers: [],
      checklist: {
        items: [{ key: "ssl_installed", label: "SSL installed", completed: false }],
        completed_count: 0,
        total_count: 14,
        progress_pct: 0,
        ready_for_production: false,
      },
      timeline: {
        last_backup: null,
        last_verify_backup: null,
        last_pricing_audit: null,
        last_preflight: null,
        last_financial_close: null,
        last_settlement: null,
        last_scheduler_heartbeat: "2026-07-14T11:59:00+01:00",
      },
      launch_mode: {
        mode: "staging",
        daily_usage: {
          transaction_count: 0,
          gross_collection_naira: 0,
          transaction_limit_daily: 100,
          revenue_limit_daily: 200000,
        },
      },
      provider_mode: {
        paystack: {
          mode: "test",
          callback_url: "https://example.com/callback",
          webhook_route: "/api/v1/payments/paystack/webhook",
          configuration_complete: true,
        },
        vtpass: { mode: "sandbox", configuration_complete: true },
      },
      security: { app_debug: false, https_app_url: true, cors_origins: ["https://paylity.ng"] },
      finance: {
        negative_margin_count: 0,
        paystack_clearing_kobo: 0,
        settlement_difference_kobo: 0,
      },
      pricing_audit_summary: { negative_margin_count: 0, all_positive: true },
      payment_certification: {
        paystack_mode: "test",
        provider_mode: "sandbox",
        vtpass_mode: "sandbox",
        environment: "staging",
        launch_mode: "staging",
        preflight_verdict: "UNKNOWN",
        active_run: null,
        last_certified_transaction: null,
        last_certification_verdict: null,
        daily_transaction_usage: {
          transaction_count: 0,
          transaction_limit_daily: 100,
          transaction_utilization_pct: null,
        },
        daily_revenue_usage: {
          gross_collection_naira: 0,
          revenue_limit_daily: 200000,
          revenue_utilization_pct: null,
        },
        daily_usage: {
          transaction_count: 0,
          gross_collection_naira: 0,
          transaction_limit_daily: 100,
          revenue_limit_daily: 200000,
        },
        last_backup_at: null,
        scheduler_health: "healthy",
      },
    },
    loading: false,
    error: null,
    refresh: vi.fn(),
  }),
}));

describe("GoLiveClient", () => {
  it("renders go-live center sections", () => {
    render(<GoLiveClient />);

    expect(screen.getByRole("heading", { name: "Go-Live Center" })).toBeInTheDocument();
    expect(screen.getByText("Scheduler Health")).toBeInTheDocument();
    expect(screen.getByText("Launch Blockers")).toBeInTheDocument();
    expect(screen.getByText("Detailed Preflight")).toBeInTheDocument();
    expect(screen.getByText("Production Checklist")).toBeInTheDocument();
    expect(screen.getByText("Launch Timeline")).toBeInTheDocument();
    expect(screen.getByText("No launch blockers detected.")).toBeInTheDocument();
    expect(screen.getByText("Production Mode")).toBeInTheDocument();
    expect(screen.getByText("Live Payment Certification")).toBeInTheDocument();
    expect(screen.getByText("Create Certification Session")).toBeInTheDocument();
  });
});
