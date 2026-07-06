export type ToastVariant = "default" | "success" | "error";

export type ToastMessage = {
  id: string;
  title: string;
  variant: ToastVariant;
};

export type ToastInput = {
  title: string;
  variant?: ToastVariant;
};

export const TOAST_MESSAGES = {
  copied: "Copied",
  downloadStarted: "Download started",
  receiptReady: "Receipt ready",
  shared: "Shared",
  verificationCopied: "Verification copied",
  paymentConfirmed: "Payment confirmed",
} as const;
