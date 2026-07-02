import type { CheckoutFields, ProductType } from "@/lib/checkout/types";
import { apiRequest } from "./client";

export type InitializeCheckoutRequest = {
  product_type: ProductType;
  customer_phone: string;
  customer_email?: string;
  customer_name?: string;
  product_amount: number;
  payload: Record<string, unknown>;
};

export type InitializeCheckoutResponse = {
  reference: string;
  product_type: ProductType;
  product_amount: number;
  convenience_fee: number;
  gateway_fee: number;
  payable_amount: number;
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
): InitializeCheckoutRequest {
  const recipientPhone = fields.useMyNumber
    ? fields.customerPhone
    : fields.recipientPhone;

  const base = {
    product_type: product,
    customer_phone: fields.customerPhone,
    customer_email: fields.customerEmail || undefined,
    customer_name: fields.customerName || undefined,
    product_amount: productAmount,
    payload: {} as Record<string, unknown>,
  };

  if (product === "airtime") {
    base.payload = {
      network: fields.network,
      recipient_phone: recipientPhone,
      use_my_number: fields.useMyNumber,
    };
  }

  if (product === "data") {
    base.payload = {
      network: fields.network,
      recipient_phone: recipientPhone,
      data_plan_id: fields.dataPlan,
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
