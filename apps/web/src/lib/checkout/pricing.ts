import { formatNaira } from "./formatNaira";

/** Maximum product amount for guest checkout (excludes fees). */
export const GUEST_MAX_PRODUCT_AMOUNT = 10_000;

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

export function isOverGuestProductLimit(productAmount: number): boolean {
  return productAmount > GUEST_MAX_PRODUCT_AMOUNT;
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
