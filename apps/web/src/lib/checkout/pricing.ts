import { formatNaira } from "./formatNaira";

/** Amounts up to this value do not require OTP. */
export const GUEST_OTP_THRESHOLD = 10_000;

/** Maximum unverified guest product amount when OTP verified. */
export const GUEST_HARD_LIMIT = 20_000;

/** @deprecated Use GUEST_OTP_THRESHOLD or GUEST_HARD_LIMIT explicitly. */
export const GUEST_MAX_PRODUCT_AMOUNT = GUEST_HARD_LIMIT;

/** Minimum product amount for airtime/electricity. */
export const MIN_PRODUCT_AMOUNT = 50;

/** Flat PAYLITY convenience fee (all products). */
export const CONVENIENCE_FEE = 100;

/** Gateway charge passed to customer. Zero until Paystack integration. */
export const GATEWAY_FEE = 0;

/** When false, UI shows "Calculated securely during payment" for gateway line. */
export const IS_GATEWAY_FEE_KNOWN = false;

export function calculatePayableAmount(
  productAmount: number,
  gatewayFee: number = GATEWAY_FEE,
): number {
  return productAmount + CONVENIENCE_FEE + gatewayFee;
}

export function requiresOtpVerification(productAmount: number): boolean {
  return productAmount > GUEST_OTP_THRESHOLD && productAmount <= GUEST_HARD_LIMIT;
}

export function isOverGuestHardLimit(productAmount: number): boolean {
  return productAmount > GUEST_HARD_LIMIT;
}

export function isOverGuestProductLimit(productAmount: number): boolean {
  return isOverGuestHardLimit(productAmount);
}

export function formatGatewayFeeLabel(
  gatewayFee: number,
  isKnown: boolean = IS_GATEWAY_FEE_KNOWN,
): string {
  if (!isKnown) {
    return "Applied securely at checkout";
  }

  return formatNaira(gatewayFee);
}
