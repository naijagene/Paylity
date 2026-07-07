import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { describe, expect, it, vi, beforeEach } from "vitest";
import { OtpVerificationGate } from "@/components/checkout/OtpVerificationGate";
import { ApiError } from "@/lib/api/client";
import * as otpApi from "@/lib/api/otp";

vi.mock("@/lib/api/otp", () => ({
  requestOtp: vi.fn(),
  verifyOtp: vi.fn(),
  resendOtp: vi.fn(),
}));

describe("OtpVerificationGate", () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(otpApi.verifyOtp).mockResolvedValue({
      verified: true,
      verification_token: "token-123",
    });
  });

  it("requests OTP on mount when no session exists", async () => {
    vi.mocked(otpApi.requestOtp).mockResolvedValue({
      otp_reference: "OTP-TEST123",
      expires_at: new Date(Date.now() + 600_000).toISOString(),
      resend_available_at: new Date(Date.now() + 60_000).toISOString(),
      masked_phone: "0803****567",
    });

    const onOtpSession = vi.fn();

    render(
      <OtpVerificationGate
        phone="08031234567"
        product="airtime"
        productAmount={15000}
        otpReference={null}
        maskedPhone={null}
        resendAvailableAt={null}
        onVerified={vi.fn()}
        onOtpSession={onOtpSession}
      />,
    );

    await waitFor(() => {
      expect(otpApi.requestOtp).toHaveBeenCalledWith({
        phone: "08031234567",
        purpose: "checkout",
        amount: 15000,
        product_type: "airtime",
      });
      expect(onOtpSession).toHaveBeenCalled();
    });
  });

  it("verifies code and calls onVerified", async () => {
    const onVerified = vi.fn();

    render(
      <OtpVerificationGate
        phone="08031234567"
        product="airtime"
        productAmount={15000}
        otpReference="OTP-TEST123"
        maskedPhone="0803****567"
        resendAvailableAt={new Date(Date.now() + 60_000).toISOString()}
        onVerified={onVerified}
        onOtpSession={vi.fn()}
      />,
    );

    fireEvent.change(screen.getByLabelText("Six digit verification code"), {
      target: { value: "123456" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Verify & Continue" }));

    await waitFor(() => {
      expect(otpApi.verifyOtp).toHaveBeenCalledWith("OTP-TEST123", "123456");
      expect(onVerified).toHaveBeenCalled();
    });
  });

  it("shows resend countdown label", () => {
    render(
      <OtpVerificationGate
        phone="08031234567"
        product="airtime"
        productAmount={15000}
        otpReference="OTP-EXISTING"
        maskedPhone="0803****567"
        resendAvailableAt={new Date(Date.now() + 30_000).toISOString()}
        onVerified={vi.fn()}
        onOtpSession={vi.fn()}
      />,
    );

    expect(screen.getByRole("button", { name: /Resend code in/i })).toBeDisabled();
  });

  it("shows invalid code error from API", async () => {
    vi.mocked(otpApi.verifyOtp).mockRejectedValueOnce(
      new ApiError("Invalid verification code.", { code: "OTP_INVALID" }, 422),
    );

    render(
      <OtpVerificationGate
        phone="08031234567"
        product="airtime"
        productAmount={15000}
        otpReference="OTP-EXISTING"
        maskedPhone="0803****567"
        resendAvailableAt={new Date(Date.now() + 60_000).toISOString()}
        onVerified={vi.fn()}
        onOtpSession={vi.fn()}
      />,
    );

    fireEvent.change(screen.getByLabelText("Six digit verification code"), {
      target: { value: "654321" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Verify & Continue" }));

    await waitFor(() => {
      expect(screen.getByText("Invalid verification code.")).toBeInTheDocument();
    });
  });
});
