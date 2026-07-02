import type { DataPlan } from "./types";

export const GUEST_MAX_AMOUNT = 10_000;
export const MIN_AMOUNT = 50;
export const CHECKOUT_STORAGE_KEY = "paylity-checkout-state";

export const WHATSAPP_URL =
  "https://wa.me/2348000000000?text=Hi%20PAYLITY%20NG%2C%20I%20need%20help";

export const NETWORKS = ["MTN", "Airtel", "Glo", "9mobile"] as const;

export const DISCOS = [
  "AEDC",
  "EKEDC",
  "IKEDC",
  "PHED",
  "IBEDC",
  "KEDCO",
] as const;

export const AIRTIME_AMOUNTS = [100, 200, 500, 1000, 2000, 5000] as const;

export const ELECTRICITY_AMOUNTS = [1000, 2000, 5000, 10000] as const;

export const DATA_PLANS: DataPlan[] = [
  {
    id: "mtn-1gb-daily",
    network: "MTN",
    name: "1GB Daily",
    size: "1GB",
    validity: "1 day",
    price: 350,
  },
  {
    id: "mtn-2gb-weekly",
    network: "MTN",
    name: "2GB Weekly",
    size: "2GB",
    validity: "7 days",
    price: 750,
  },
  {
    id: "mtn-5gb-monthly",
    network: "MTN",
    name: "5GB Monthly",
    size: "5GB",
    validity: "30 days",
    price: 2500,
  },
  {
    id: "airtel-1gb-daily",
    network: "Airtel",
    name: "1GB Daily",
    size: "1GB",
    validity: "1 day",
    price: 300,
  },
  {
    id: "airtel-3gb-weekly",
    network: "Airtel",
    name: "3GB Weekly",
    size: "3GB",
    validity: "7 days",
    price: 1000,
  },
  {
    id: "glo-1gb-daily",
    network: "Glo",
    name: "1GB Daily",
    size: "1GB",
    validity: "1 day",
    price: 280,
  },
  {
    id: "glo-2gb-weekly",
    network: "Glo",
    name: "2GB Weekly",
    size: "2GB",
    validity: "7 days",
    price: 700,
  },
  {
    id: "9mobile-1gb-daily",
    network: "9mobile",
    name: "1GB Daily",
    size: "1GB",
    validity: "1 day",
    price: 250,
  },
];

export const MOCK_METER_NAMES: Record<string, string> = {
  default: "John Doe",
};
