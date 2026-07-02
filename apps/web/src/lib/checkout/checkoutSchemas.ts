import type { ProductSchema, ProductType } from "./types";

export const PRODUCT_SCHEMAS: ProductSchema[] = [
  {
    id: "airtime",
    label: "Buy Airtime",
    amountMode: "quick-picks",
    guestMaxProductAmount: 10_000,
  },
  {
    id: "data",
    label: "Buy Data",
    amountMode: "plan-picker",
    guestMaxProductAmount: 10_000,
  },
  {
    id: "electricity",
    label: "Pay Electricity",
    amountMode: "quick-picks",
    guestMaxProductAmount: 10_000,
  },
];

export const DEFAULT_PRODUCT: ProductType = "airtime";

export function isValidProduct(value: string | null | undefined): value is ProductType {
  return value === "airtime" || value === "data" || value === "electricity";
}

export function getProductSchema(product: ProductType): ProductSchema {
  return (
    PRODUCT_SCHEMAS.find((schema) => schema.id === product) ?? PRODUCT_SCHEMAS[0]
  );
}

export function resolveProduct(value: string | null | undefined): ProductType {
  return isValidProduct(value) ? value : DEFAULT_PRODUCT;
}
