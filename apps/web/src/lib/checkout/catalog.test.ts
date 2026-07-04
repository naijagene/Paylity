import { describe, expect, it } from "vitest";
import { buildInitializeCheckoutPayload } from "@/lib/api/checkout";
import type { ProductCatalog } from "@/lib/api/catalog";
import {
  canInitializeCheckout,
  findCatalogDataPlan,
  getCatalogDataPlansForNetwork,
  hasCatalogDataVariations,
} from "@/lib/checkout/catalogPlans";

const sampleCatalog: ProductCatalog = {
  categories: [{ key: "data", name: "Data", is_active: true }],
  provider: "vtpass",
  catalog_meta: {
    total_variations: 2,
    visible_variations: 1,
    hidden_variations: 1,
  },
  data_services: [
    {
      service_name: "mtn",
      service_id: "mtn-data",
      display_name: "MTN",
      network: "MTN",
      variations: [
        {
          variation_code: "mtn-500mb-30days",
          name: "MTN Data - 500 Naira - 500MB - 30 days",
          display_name: "500MB - 30 Days",
          amount: 500,
          fixed_price: true,
          is_popular: true,
          validity_label: "30 Days",
          data_size_label: "500MB",
          sort_order: 30000500,
        },
        {
          variation_code: "mtn-5gb-30days",
          name: "MTN Data - 5000 Naira - 5GB - 30 days",
          display_name: "5GB - 30 Days",
          amount: 5000,
          fixed_price: true,
          validity_label: "30 Days",
          data_size_label: "5GB",
          sort_order: 30005000,
        },
      ],
    },
  ],
};

describe("catalogPlans", () => {
  it("detects active visible data variations", () => {
    expect(hasCatalogDataVariations(sampleCatalog)).toBe(true);
    expect(hasCatalogDataVariations({ ...sampleCatalog, data_services: [] })).toBe(
      false,
    );
  });

  it("maps and sorts catalog data plans by amount ascending", () => {
    const plans = getCatalogDataPlansForNetwork(sampleCatalog, "MTN");

    expect(plans).toHaveLength(2);
    expect(plans[0]?.variationCode).toBe("mtn-500mb-30days");
    expect(plans[1]?.variationCode).toBe("mtn-5gb-30days");
    expect(plans[0]?.displayName).toBe("500MB - 30 Days");
  });

  it("blocks data checkout when catalog has no visible variations", () => {
    expect(
      canInitializeCheckout("data", { ...sampleCatalog, data_services: [] }, false),
    ).toEqual({
      allowed: false,
      message:
        "Product catalog is unavailable. Please refresh the page and try again.",
    });
  });

  it("allows data checkout when catalog has visible variations", () => {
    expect(canInitializeCheckout("data", sampleCatalog, false)).toEqual({
      allowed: true,
    });
  });
});

describe("buildInitializeCheckoutPayload", () => {
  it("sends catalog display name and variation_code for data", () => {
    const payload = buildInitializeCheckoutPayload(
      "data",
      {
        customerPhone: "08031234567",
        customerEmail: "",
        network: "MTN",
        recipientPhone: "08031234567",
        dataPlan: "mtn-500mb-30days",
        disco: "",
        meterType: "prepaid",
        meterNumber: "",
        customerName: "",
        useMyNumber: true,
        meterVerified: false,
      },
      500,
      sampleCatalog,
    );

    expect(payload.payload).toMatchObject({
      network: "MTN",
      recipient_phone: "08031234567",
      variation_code: "mtn-500mb-30days",
      service_id: "mtn-data",
      plan_name: "500MB - 30 Days",
    });

    expect(findCatalogDataPlan(sampleCatalog, "MTN", "mtn-500mb-30days")?.price).toBe(
      500,
    );
  });
});
