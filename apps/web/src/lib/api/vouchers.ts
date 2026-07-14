import { apiRequest } from "./client";
import { getDeviceId } from "@/lib/checkout/deviceId";
import type { ProductType } from "@/lib/checkout/types";

export type ValidateVoucherResponse = {
  valid: boolean;
  code: string;
  name: string;
  voucher_amount: number;
  discount_amount: number;
  product_amount: number;
  convenience_fee: number;
  gateway_fee: number;
  payable_amount: number;
  remaining_redemptions: number;
};

export async function validateVoucher(input: {
  code: string;
  product_type: ProductType;
  product_amount: number;
  network?: string;
  customer_phone?: string;
  customer_email?: string;
}): Promise<ValidateVoucherResponse> {
  const { data } = await apiRequest<ValidateVoucherResponse>("/vouchers/validate", {
    method: "POST",
    body: JSON.stringify({
      ...input,
      device_id: getDeviceId(),
    }),
  });

  return data;
}
