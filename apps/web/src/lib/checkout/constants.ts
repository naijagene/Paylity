export const CHECKOUT_STORAGE_KEY = "paylity-checkout-state";

export const AIRTIME_AMOUNTS = [100, 200, 500, 1000, 2000, 5000] as const;

export const ELECTRICITY_AMOUNTS = [1000, 2000, 5000, 10000] as const;

export {
  CONVENIENCE_FEE,
  GATEWAY_FEE,
  GUEST_MAX_PRODUCT_AMOUNT,
  IS_GATEWAY_FEE_KNOWN,
  MIN_PRODUCT_AMOUNT,
} from "./pricing";
