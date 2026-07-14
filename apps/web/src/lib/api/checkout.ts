import type { CheckoutFields, ProductType } from "@/lib/checkout/types";
import type { ProductCatalog } from "@/lib/api/catalog";
import { findCatalogDataPlan } from "@/lib/checkout/catalogPlans";
import { apiRequest } from "./client";

export type InitializeCheckoutRequest = {
  product_type: ProductType;
  customer_phone: string;
  customer_email?: string;
  customer_name?: string;
  product_amount: number;
  payload: Record<string, unknown>;
  verification_token?: string;
  voucher_code?: string;
  device_id?: string;
};

export type InitializeCheckoutResponse = {
  reference: string;
  product_type: ProductType;
  product_amount: number;
  convenience_fee: number;
  gateway_fee: number;
  payable_amount: number;
  voucher_code?: string | null;
  voucher_discount_amount?: number;
  currency: string;
  status: string;
  payment_provider?: string | null;
  authorization_url?: string;
  access_code?: string;
  payment_status?: string;
};

export function buildInitializeCheckoutPayload(
  product: ProductType,
  fields: CheckoutFields,
  productAmount: number,
  catalog?: ProductCatalog | null,
  verificationToken?: string | null,
  voucherCode?: string | null,
  deviceId?: string | null,
): InitializeCheckoutRequest {
  const recipientPhone = fields.useMyNumber
    ? fields.customerPhone
    : fields.recipientPhone;

  const base: InitializeCheckoutRequest = {
    product_type: product,
    customer_phone: fields.customerPhone,
    customer_email: fields.customerEmail || undefined,
    customer_name: fields.customerName || undefined,
    product_amount: productAmount,
    payload: {},
  };

  if (product === "airtime") {
    base.payload = {
      network: fields.network,
      recipient_phone: recipientPhone,
      use_my_number: fields.useMyNumber,
    };
  }

  if (product === "data") {
    const plan = findCatalogDataPlan(catalog ?? null, fields.network, fields.dataPlan);

    base.payload = {
      network: fields.network,
      recipient_phone: recipientPhone,
      variation_code: fields.dataPlan,
      service_id: plan?.serviceId,
      plan_name: plan?.displayName ?? plan?.name,
    };
  }

  if (product === "electricity") {
    base.payload = {
      disco: fields.disco,
      meter_type: fields.meterType,
      meter_number: fields.meterNumber,
      customer_name: fields.customerName,
    };
  }

  if (verificationToken) {
    base.verification_token = verificationToken;
  }

  if (voucherCode) {
    base.voucher_code = voucherCode;
  }

  if (deviceId) {
    base.device_id = deviceId;
  }

  return base;
}

export async function initializeCheckout(
  payload: InitializeCheckoutRequest,
): Promise<InitializeCheckoutResponse> {
  const { data } = await apiRequest<InitializeCheckoutResponse>(
    "/checkout/initialize",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );

  return data;
}
