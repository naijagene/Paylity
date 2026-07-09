import type { ProductCatalog } from "@/lib/api/catalog";
import type { ProductType } from "./types";

export type CatalogDataPlan = {
  variationCode: string;
  serviceId: string;
  network: string;
  name: string;
  displayName: string;
  providerName: string;
  price: number;
  fixedPrice: boolean;
  isPopular: boolean;
  validityLabel?: string | null;
  dataSizeLabel?: string | null;
  sortOrder?: number | null;
};

const CATALOG_UNAVAILABLE_MESSAGE =
  "Product catalog is unavailable. Please refresh the page and try again.";

function mapVariationToPlan(
  service: NonNullable<ProductCatalog["data_services"]>[number],
  variation: NonNullable<ProductCatalog["data_services"]>[number]["variations"][number],
): CatalogDataPlan | null {
  if (variation.amount === null || variation.amount <= 0) {
    return null;
  }

  const displayName = variation.display_name || variation.name;

  return {
    variationCode: variation.variation_code,
    serviceId: service.service_id,
    network: service.network,
    name: displayName,
    displayName,
    providerName: variation.name,
    price: variation.amount,
    fixedPrice: variation.fixed_price,
    isPopular: variation.is_popular ?? false,
    validityLabel: variation.validity_label,
    dataSizeLabel: variation.data_size_label,
    sortOrder: variation.sort_order,
  };
}

function sortPlans(plans: CatalogDataPlan[]): CatalogDataPlan[] {
  return [...plans].sort((left, right) => {
    if (left.price !== right.price) {
      return left.price - right.price;
    }

    const leftOrder = left.sortOrder ?? Number.MAX_SAFE_INTEGER;
    const rightOrder = right.sortOrder ?? Number.MAX_SAFE_INTEGER;

    return leftOrder - rightOrder;
  });
}

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

  const plans = service.variations
    .map((variation) => mapVariationToPlan(service, variation))
    .filter((plan): plan is CatalogDataPlan => plan !== null);

  return sortPlans(plans);
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

export function resolveDataPlansForNetwork(
  catalog: ProductCatalog | null,
  network: string,
): CatalogDataPlan[] {
  return getCatalogDataPlansForNetwork(catalog, network);
}

export function getCatalogNetworks(
  catalog: ProductCatalog | null,
  product: Extract<ProductType, "airtime" | "data">,
): string[] {
  if (product === "data") {
    const networks = (catalog?.data_services ?? []).map((item) => item.network);

    return [...new Set(networks)];
  }

  return (catalog?.airtime_networks ?? []).map((item) => item.network);
}

export function getCatalogDiscos(
  catalog: ProductCatalog | null,
): Array<{ value: string; label: string }> {
  return (catalog?.electricity_discos ?? []).map((item) => ({
    value: item.disco,
    label: item.display_name || item.disco,
  }));
}

export function canInitializeCheckout(
  product: ProductType,
  catalog: ProductCatalog | null,
  catalogLoading: boolean,
): { allowed: boolean; message?: string } {
  if (catalogLoading) {
    return {
      allowed: false,
      message: "Loading product catalog…",
    };
  }

  if (product === "data") {
    if (!hasCatalogDataVariations(catalog)) {
      return {
        allowed: false,
        message: CATALOG_UNAVAILABLE_MESSAGE,
      };
    }

    return { allowed: true };
  }

  if (product === "airtime") {
    if ((catalog?.airtime_networks ?? []).length === 0) {
      return {
        allowed: false,
        message: CATALOG_UNAVAILABLE_MESSAGE,
      };
    }

    return { allowed: true };
  }

  if ((catalog?.electricity_discos ?? []).length === 0) {
    return {
      allowed: false,
      message: CATALOG_UNAVAILABLE_MESSAGE,
    };
  }

  return { allowed: true };
}
