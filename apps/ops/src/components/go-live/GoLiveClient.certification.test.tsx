import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { defaultPaymentCertificationState } from "@/lib/api/ops";

const mockUsePolling = vi.fn();
const opsPaymentCertificationPreflight = vi.fn();
const opsPaymentCertificationCreate = vi.fn();

vi.mock("@/lib/hooks/usePolling", () => ({
  usePolling: (...args: unknown[]) => mockUsePolling(...args),
}));

vi.mock("@/lib/api/ops", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api/ops")>();
  return {
    ...actual,
    fetchOpsGoLive: vi.fn(),
    opsPaymentCertificationPreflight: (...args: unknown[]) => opsPaymentCertificationPreflight(...args),
    opsPaymentCertificationCreate: (...args: unknown[]) => opsPaymentCertificationCreate(...args),
  };
});

import { GoLiveClient } from "@/components/go-live/GoLiveClient";

const baseSnapshot = {
  refreshed_at: "2026-07-14T12:00:00+01:00",
  launch_status: {
    status: "READY_WITH_WARNINGS",
    environment: "production",
    environment_badge: { label: "Production", variant: "success" as const },
    version: "1.0.0",
    build: "test-build",
    scheduler: {
      status: "healthy",
      last_run: "2026-07-14T11:59:00+01:00",
      seconds_since_last_run: 60,
      next_expected_run: "2026-07-14T12:00:00+01:00",
    },
    backup: { last_run_at: "2026-07-14T10:00:00+01:00" },
  },
  preflight: {
    status: "READY_WITH_WARNINGS",
    summary: { pass: 18, warn: 2, fail: 0 },
    checks: [],
  },
  blockers: [],
  checklist: {
    items: [],
    completed_count: 0,
    total_count: 14,
    progress_pct: 0,
    ready_for_production: false,
  },
  timeline: {},
  launch_mode: {
    mode: "soft_launch",
    daily_usage: {
      transaction_count: 2,
      gross_collection_naira: 500,
      transaction_limit_daily: 100,
      revenue_limit_daily: 200000,
    },
  },
  provider_mode: {
    paystack: {
      mode: "live",
      callback_url: "https://paylity.ng/callback",
      webhook_route: "/api/v1/payments/paystack/webhook",
      configuration_complete: true,
    },
    vtpass: { mode: "live", configuration_complete: true },
  },
  security: { app_debug: false, https_app_url: true, cors_origins: ["https://paylity.ng"] },
  finance: {
    negative_margin_count: 0,
    paystack_clearing_kobo: 0,
    settlement_difference_kobo: 0,
  },
  pricing_audit_summary: { negative_margin_count: 0, all_positive: true },
  payment_certification: defaultPaymentCertificationState({
    paystack_mode: "live",
    provider_mode: "live",
    environment: "production",
    launch_mode: "soft_launch",
    preflight_verdict: "READY",
  }),
};

describe("GoLiveClient Live Payment Certification", () => {
  beforeEach(() => {
    mockUsePolling.mockReturnValue({
      data: baseSnapshot,
      loading: false,
      error: null,
      refresh: vi.fn().mockResolvedValue(undefined),
    });
    opsPaymentCertificationPreflight.mockResolvedValue({});
    opsPaymentCertificationCreate.mockResolvedValue({ id: 1, result: "INCOMPLETE" });
  });

  it("renders certification heading with full snapshot", () => {
    render(<GoLiveClient />);

    expect(screen.getByRole("heading", { name: "Live Payment Certification" })).toBeInTheDocument();
    expect(screen.getByText("Run Live Payment Preflight")).toBeInTheDocument();
    expect(screen.getByText("Scheduler Health")).toBeInTheDocument();
    expect(screen.getByText("Quick Links")).toBeInTheDocument();
  });

  it("renders certification heading when payment_certification is null", () => {
    mockUsePolling.mockReturnValue({
      data: { ...baseSnapshot, payment_certification: null },
      loading: false,
      error: null,
      refresh: vi.fn(),
    });

    render(<GoLiveClient />);

    expect(screen.getByRole("heading", { name: "Live Payment Certification" })).toBeInTheDocument();
    expect(screen.getByText("Certification status is currently unavailable.")).toBeInTheDocument();
  });

  it("renders certification heading when payment_certification is omitted", () => {
    const { payment_certification: _removed, ...withoutCertification } = baseSnapshot;
    mockUsePolling.mockReturnValue({
      data: withoutCertification,
      loading: false,
      error: null,
      refresh: vi.fn(),
    });

    render(<GoLiveClient />);

    expect(screen.getByRole("heading", { name: "Live Payment Certification" })).toBeInTheDocument();
    expect(screen.getByText("No active certification session.")).toBeInTheDocument();
  });

  it("keeps certification section visible when polling error exists", () => {
    mockUsePolling.mockReturnValue({
      data: baseSnapshot,
      loading: false,
      error: "Network failure",
      refresh: vi.fn(),
    });

    render(<GoLiveClient />);

    expect(screen.getByRole("heading", { name: "Live Payment Certification" })).toBeInTheDocument();
    expect(screen.getByText("Network failure")).toBeInTheDocument();
  });

  it("calls preflight endpoint from action button", async () => {
    render(<GoLiveClient />);

    fireEvent.click(screen.getByRole("button", { name: "Run Live Payment Preflight" }));

    await waitFor(() => {
      expect(opsPaymentCertificationPreflight).toHaveBeenCalledWith(false);
    });
  });

  it("requires confirmation before creating certification session", async () => {
    render(<GoLiveClient />);

    fireEvent.click(screen.getByRole("button", { name: "Create Certification Session" }));
    fireEvent.click(screen.getByRole("button", { name: "Create Session" }));
    expect(opsPaymentCertificationCreate).not.toHaveBeenCalled();

    fireEvent.click(screen.getByLabelText("Confirm live certification session"));
    fireEvent.click(screen.getByRole("button", { name: "Create Session" }));

    await waitFor(() => {
      expect(opsPaymentCertificationCreate).toHaveBeenCalledWith(
        expect.objectContaining({ confirm_live_certification: true, amount: 100, product: "airtime" }),
      );
    });
  });

  it("keeps maintenance and soft-launch actions available", () => {
    render(<GoLiveClient />);

    expect(screen.getAllByRole("button", { name: "Enter Maintenance Mode" }).length).toBeGreaterThan(0);
    expect(screen.getAllByRole("button", { name: "Restore Soft Launch Mode" }).length).toBeGreaterThan(0);
  });
});
