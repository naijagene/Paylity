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
  categories: [
    { key: "data", name: "Data", is_active: true },
  ],
  provider: "vtpass",
  data_services: [
    {
      service_name: "mtn",
      service_id: "mtn-data",
      display_name: "MTN",
      network: "MTN",
      variations: [
        {
          variation_code: "mtn-10mb-100",
          name: "MTN 10MB",
          amount: 100,
          fixed_price: true,
        },
      ],
    },
  ],
};

describe("catalogPlans", () => {
  it("detects active data variations", () => {
    expect(hasCatalogDataVariations(sampleCatalog)).toBe(true);
    expect(hasCatalogDataVariations({ ...sampleCatalog, data_services: [] })).toBe(
      false,
    );
  });

  it("maps catalog data plans for a network", () => {
    const plans = getCatalogDataPlansForNetwork(sampleCatalog, "MTN");

    expect(plans).toHaveLength(1);
    expect(plans[0]).toMatchObject({
      variationCode: "mtn-10mb-100",
      serviceId: "mtn-data",
      price: 100,
    });
  });

  it("blocks data checkout when catalog has no variations", () => {
    expect(
      canInitializeCheckout("data", { ...sampleCatalog, data_services: [] }, false),
    ).toEqual({
      allowed: false,
      message:
        "Product catalog is unavailable. Please refresh the page and try again.",
    });
  });

  it("allows data checkout when catalog has variations", () => {
    expect(canInitializeCheckout("data", sampleCatalog, false)).toEqual({
      allowed: true,
    });
  });
});

describe("buildInitializeCheckoutPayload", () => {
  it("sends catalog variation_code, service_id, and plan_name for data", () => {
    const payload = buildInitializeCheckoutPayload(
      "data",
      {
        customerPhone: "08031234567",
        customerEmail: "",
        network: "MTN",
        recipientPhone: "08031234567",
        dataPlan: "mtn-10mb-100",
        disco: "",
        meterType: "prepaid",
        meterNumber: "",
        customerName: "",
        useMyNumber: true,
        meterVerified: false,
      },
      100,
      sampleCatalog,
    );

    expect(payload.payload).toMatchObject({
      network: "MTN",
      recipient_phone: "08031234567",
      variation_code: "mtn-10mb-100",
      service_id: "mtn-data",
      plan_name: "MTN 10MB",
    });

    expect(findCatalogDataPlan(sampleCatalog, "MTN", "mtn-10mb-100")?.price).toBe(
      100,
    );
  });
});
