import { apiRequest } from "./client";

export type OtpPurpose =
  | "checkout"
  | "registration"
  | "wallet"
  | "password_reset"
  | "sensitive_action";

export type OtpRequestPayload = {
  phone: string;
  purpose: OtpPurpose;
  reference?: string;
  amount?: number;
  product_type?: string;
  email?: string;
};

export type OtpRequestResponse = {
  otp_reference: string;
  expires_at: string;
  resend_available_at: string;
  masked_phone: string;
};

export type OtpVerifyResponse = {
  verified: boolean;
  verification_token: string;
};

export async function requestOtp(payload: OtpRequestPayload) {
  const { data } = await apiRequest<OtpRequestResponse>("/otp/request", {
    method: "POST",
    body: JSON.stringify(payload),
  });

  return data;
}

export async function verifyOtp(otpReference: string, code: string) {
  const { data } = await apiRequest<OtpVerifyResponse>("/otp/verify", {
    method: "POST",
    body: JSON.stringify({
      otp_reference: otpReference,
      code,
    }),
  });

  return data;
}

export async function resendOtp(otpReference: string) {
  const { data } = await apiRequest<OtpRequestResponse>("/otp/resend", {
    method: "POST",
    body: JSON.stringify({
      otp_reference: otpReference,
    }),
  });

  return data;
}
