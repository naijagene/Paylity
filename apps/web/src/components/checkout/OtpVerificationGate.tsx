"use client";

import { useEffect, useMemo, useState } from "react";
import { Button } from "@/components/Button";
import { requestOtp, resendOtp, verifyOtp } from "@/lib/api/otp";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import type { ProductType } from "@/lib/checkout/types";

type OtpVerificationGateProps = {
  phone: string;
  product: ProductType;
  productAmount: number;
  otpReference: string | null;
  maskedPhone: string | null;
  resendAvailableAt: string | null;
  onVerified: (payload: {
    verificationToken: string;
    otpReference: string;
    maskedPhone: string;
    otpResendAvailableAt: string;
  }) => void;
  onOtpSession: (payload: {
    otpReference: string;
    maskedPhone: string;
    otpResendAvailableAt: string;
  }) => void;
};

function secondsUntil(isoDate: string | null): number {
  if (!isoDate) {
    return 0;
  }

  return Math.max(0, Math.ceil((new Date(isoDate).getTime() - Date.now()) / 1000));
}

export function OtpVerificationGate({
  phone,
  product,
  productAmount,
  otpReference,
  maskedPhone,
  resendAvailableAt,
  onVerified,
  onOtpSession,
}: OtpVerificationGateProps) {
  const [code, setCode] = useState("");
  const [loading, setLoading] = useState(false);
  const [requesting, setRequesting] = useState(() => !otpReference);
  const [error, setError] = useState<string | null>(null);
  const [countdown, setCountdown] = useState(secondsUntil(resendAvailableAt));
  const showStagingHint =
    process.env.NODE_ENV !== "production" &&
    process.env.NEXT_PUBLIC_VERCEL_ENV !== "production";

  useEffect(() => {
    if (otpReference) {
      return;
    }

    let cancelled = false;

    requestOtp({
      phone,
      purpose: "checkout",
      amount: productAmount,
      product_type: product,
    })
      .then((response) => {
        if (cancelled) {
          return;
        }

        onOtpSession({
          otpReference: response.otp_reference,
          maskedPhone: response.masked_phone,
          otpResendAvailableAt: response.resend_available_at,
        });
        setCountdown(secondsUntil(response.resend_available_at));
      })
      .catch((err) => {
        if (cancelled) {
          return;
        }

        if (err instanceof ApiOfflineError) {
          setError(err.message);
        } else if (err instanceof ApiError) {
          setError(err.message);
        } else {
          setError("Unable to send verification code.");
        }
      })
      .finally(() => {
        if (!cancelled) {
          setRequesting(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [otpReference, onOtpSession, phone, product, productAmount]);

  useEffect(() => {
    if (countdown <= 0) {
      return;
    }

    const timer = window.setInterval(() => {
      setCountdown((current) => Math.max(0, current - 1));
    }, 1000);

    return () => window.clearInterval(timer);
  }, [countdown]);

  const displayPhone = maskedPhone ?? phone;

  const handleVerify = async () => {
    if (!otpReference || code.trim().length < 4) {
      setError("Enter the 6-digit code sent to your phone.");
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const result = await verifyOtp(otpReference, code.trim());

      onVerified({
        verificationToken: result.verification_token,
        otpReference,
        maskedPhone: displayPhone,
        otpResendAvailableAt: resendAvailableAt ?? new Date().toISOString(),
      });
    } catch (err) {
      if (err instanceof ApiOfflineError) {
        setError(err.message);
      } else if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Unable to verify code.");
      }
    } finally {
      setLoading(false);
    }
  };

  const handleResend = async () => {
    if (!otpReference || countdown > 0) {
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const response = await resendOtp(otpReference);
      onOtpSession({
        otpReference: response.otp_reference,
        maskedPhone: response.masked_phone,
        otpResendAvailableAt: response.resend_available_at,
      });
      setCountdown(secondsUntil(response.resend_available_at));
      setCode("");
    } catch (err) {
      if (err instanceof ApiOfflineError) {
        setError(err.message);
      } else if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Unable to resend verification code.");
      }
    } finally {
      setLoading(false);
    }
  };

  const helperText = useMemo(() => {
    if (requesting) {
      return "Sending verification code…";
    }

    return `Enter the 6-digit code sent to ${displayPhone}.`;
  }, [displayPhone, requesting]);

  return (
    <section className="rounded-3xl border border-border bg-card p-6 shadow-sm">
      <p className="text-sm font-semibold uppercase tracking-wide text-success">
        Phone verification
      </p>
      <h2 className="mt-2 font-display text-2xl font-extrabold text-dark">
        Verify your phone
      </h2>
      <p className="mt-3 text-sm text-muted">{helperText}</p>

      {showStagingHint ? (
        <p className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          Staging OTP is available in API logs.
        </p>
      ) : null}

      <label className="mt-6 block">
        <span className="text-sm font-semibold text-dark">Verification code</span>
        <input
          inputMode="numeric"
          autoComplete="one-time-code"
          maxLength={6}
          value={code}
          onChange={(event) => setCode(event.target.value.replace(/\D/g, "").slice(0, 6))}
          className="mt-2 w-full rounded-2xl border border-border px-4 py-3 text-center text-2xl tracking-[0.4em] outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
          aria-label="Six digit verification code"
          disabled={requesting || loading || !otpReference}
        />
      </label>

      {error ? (
        <p className="mt-3 text-sm text-error" role="alert">
          {error}
        </p>
      ) : null}

      <div className="mt-6 space-y-3">
        <Button
          type="button"
          className="w-full"
          onClick={() => void handleVerify()}
          disabled={requesting || loading || !otpReference}
        >
          {loading ? "Verifying…" : "Verify & Continue"}
        </Button>

        <Button
          type="button"
          variant="outline"
          className="w-full"
          onClick={() => void handleResend()}
          disabled={requesting || loading || countdown > 0 || !otpReference}
        >
          {countdown > 0 ? `Resend code in ${countdown}s` : "Resend code"}
        </Button>
      </div>
    </section>
  );
}
