import { apiRequest } from "./client";
import {
  fetchProductCatalog,
  type ProductCatalog,
} from "./catalog";

export type VerifyElectricityMeterRequest = {
  disco: string;
  meter_number: string;
  meter_type: "prepaid" | "postpaid";
};

export type VerifyElectricityMeterResponse = {
  verified: boolean;
  available: boolean;
  customer_name: string | null;
  meter_number: string;
  disco: string;
  message: string;
  minimum_amount?: string | null;
};

export async function fetchAirtimeNetworks(): Promise<ProductCatalog> {
  return fetchProductCatalog("airtime");
}

export async function fetchDataNetworks(): Promise<ProductCatalog> {
  return fetchProductCatalog("data");
}

export async function fetchElectricityDiscos(): Promise<ProductCatalog> {
  return fetchProductCatalog("electricity");
}

export async function verifyElectricityMeter(
  payload: VerifyElectricityMeterRequest,
): Promise<VerifyElectricityMeterResponse> {
  const { data } = await apiRequest<VerifyElectricityMeterResponse>(
    "/electricity/meter/verify",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );

  return data;
}
