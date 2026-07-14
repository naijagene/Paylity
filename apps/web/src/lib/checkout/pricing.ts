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

/** Paystack fee assumptions mirrored from backend platform settings defaults. */
export const PAYSTACK_FEE_BASIS_POINTS = 150;
export const PAYSTACK_FEE_FLAT_NAIRA = 100;

/** Gateway charge passed to customer when Paystack checkout is enabled. */
export const IS_GATEWAY_FEE_KNOWN = true;

export function calculateGatewayFee(
  productAmount: number,
  convenienceFee: number = CONVENIENCE_FEE,
): number {
  const subtotalKobo = (productAmount + convenienceFee) * 100;
  let gatewayKobo = 0;

  for (let iteration = 0; iteration < 5; iteration += 1) {
    const payableKobo = subtotalKobo + gatewayKobo;
    const percentageFee = Math.round(payableKobo * (PAYSTACK_FEE_BASIS_POINTS / 10000));
    gatewayKobo = percentageFee + PAYSTACK_FEE_FLAT_NAIRA * 100;
  }

  return Math.round(gatewayKobo / 100);
}

/** @deprecated Use calculateGatewayFee(). */
export const GATEWAY_FEE = 0;

export function calculatePayableAmount(
  productAmount: number,
  gatewayFee: number = calculateGatewayFee(productAmount),
): number {
  return productAmount + CONVENIENCE_FEE + gatewayFee;
}

export function calculatePricingWithVoucher(productAmount: number, voucherDiscountAmount = 0) {
  const discount = Math.max(0, Math.min(voucherDiscountAmount, productAmount));
  const netProductAmount = Math.max(0, productAmount - discount);
  const preGatewayCharge = netProductAmount + CONVENIENCE_FEE;
  const gatewayFee = calculateGatewayFee(netProductAmount, CONVENIENCE_FEE);

  return {
    productAmount,
    voucherDiscountAmount: discount,
    netProductAmount,
    preGatewayCharge,
    convenienceFee: CONVENIENCE_FEE,
    gatewayFee,
    payableAmount: preGatewayCharge + gatewayFee,
  };
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
