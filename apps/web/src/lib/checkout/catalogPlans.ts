import type { ProductCatalog } from "@/lib/api/catalog";
import { DATA_PLANS, DISCOS, NETWORKS } from "./constants";
import type { DataPlan } from "./types";

export type CatalogDataPlan = {
  variationCode: string;
  serviceId: string;
  network: string;
  name: string;
  price: number;
  fixedPrice: boolean;
};

export function hasCatalogDataVariations(catalog: ProductCatalog | null): boolean {
  return (catalog?.data_services ?? []).some(
    (service) => (service.variations?.length ?? 0) > 0,
  );
}

export function getCatalogDataPlansForNetwork(
  catalog: ProductCatalog | null,
  network: string,
): CatalogDataPlan[] {
  if (!catalog?.data_services?.length || !network) {
    return [];
  }

  const normalizedNetwork = network.trim().toLowerCase();

  const service = catalog.data_services.find(
    (item) => item.network.toLowerCase() === normalizedNetwork,
  );

  if (!service) {
    return [];
  }

  return service.variations
    .filter((variation) => variation.amount !== null && variation.amount > 0)
    .map((variation) => ({
      variationCode: variation.variation_code,
      serviceId: service.service_id,
      network: service.network,
      name: variation.name,
      price: variation.amount as number,
      fixedPrice: variation.fixed_price,
    }));
}

export function findCatalogDataPlan(
  catalog: ProductCatalog | null,
  network: string,
  variationCode: string,
): CatalogDataPlan | undefined {
  return getCatalogDataPlansForNetwork(catalog, network).find(
    (plan) => plan.variationCode === variationCode,
  );
}

export function getDevelopmentFallbackDataPlans(network: string): CatalogDataPlan[] {
  if (process.env.NODE_ENV !== "development") {
    return [];
  }

  return DATA_PLANS.filter((plan) => !network || plan.network === network).map(
    (plan) => ({
      variationCode: plan.id,
      serviceId: `${plan.network.toLowerCase()}-data`,
      network: plan.network,
      name: plan.name,
      price: plan.price,
      fixedPrice: true,
    }),
  );
}

export function resolveDataPlansForNetwork(
  catalog: ProductCatalog | null,
  network: string,
  allowDevFallback: boolean,
): CatalogDataPlan[] {
  const catalogPlans = getCatalogDataPlansForNetwork(catalog, network);

  if (catalogPlans.length > 0) {
    return catalogPlans;
  }

  if (allowDevFallback) {
    return getDevelopmentFallbackDataPlans(network);
  }

  return [];
}

export function getCatalogNetworks(
  catalog: ProductCatalog | null,
  allowDevFallback: boolean,
): string[] {
  const networks = (catalog?.airtime_networks ?? []).map((item) => item.network);

  if (networks.length > 0) {
    return networks;
  }

  if (allowDevFallback && process.env.NODE_ENV === "development") {
    return [...NETWORKS];
  }

  return [];
}

export function getCatalogDiscos(
  catalog: ProductCatalog | null,
  allowDevFallback: boolean,
): Array<{ value: string; label: string }> {
  const discos = (catalog?.electricity_discos ?? []).map((item) => ({
    value: item.disco,
    label: item.display_name || item.disco,
  }));

  if (discos.length > 0) {
    return discos;
  }

  if (allowDevFallback && process.env.NODE_ENV === "development") {
    return DISCOS.map((disco) => ({ value: disco, label: disco }));
  }

  return [];
}

export function canInitializeCheckout(
  product: "airtime" | "data" | "electricity",
  catalog: ProductCatalog | null,
  catalogLoading: boolean,
): { allowed: boolean; message?: string } {
  if (catalogLoading) {
    return {
      allowed: false,
      message: "Loading product catalog…",
    };
  }

  const isDev = process.env.NODE_ENV === "development";

  if (product === "data") {
    if (!hasCatalogDataVariations(catalog)) {
      return {
        allowed: false,
        message:
          "Product catalog is unavailable. Please refresh the page and try again.",
      };
    }

    return { allowed: true };
  }

  if (!catalog && !isDev) {
    return {
      allowed: false,
      message:
        "Product catalog is unavailable. Please refresh the page and try again.",
    };
  }

  return { allowed: true };
}

export function catalogDataPlanToLegacy(plan: CatalogDataPlan): DataPlan {
  return {
    id: plan.variationCode,
    network: plan.network,
    name: plan.name,
    size: plan.name,
    validity: "",
    price: plan.price,
  };
}
