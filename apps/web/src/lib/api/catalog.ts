import { apiRequest } from "./client";

export type CatalogCategory = {
  key: string;
  name: string;
  is_active: boolean;
};

export type CatalogNetworkService = {
  service_name: string;
  service_id: string;
  display_name: string;
  network: string;
};

export type CatalogDiscoService = {
  service_name: string;
  service_id: string;
  display_name: string;
  disco: string;
};

export type CatalogVariation = {
  variation_code: string;
  name: string;
  amount: number | null;
  fixed_price: boolean;
};

export type CatalogDataService = {
  service_name: string;
  service_id: string;
  display_name: string;
  network: string;
  variations: CatalogVariation[];
};

export type ProductCatalog = {
  categories: CatalogCategory[];
  provider: string;
  airtime_networks?: CatalogNetworkService[];
  data_services?: CatalogDataService[];
  electricity_discos?: CatalogDiscoService[];
};

export async function fetchProductCatalog(
  category?: "airtime" | "data" | "electricity",
): Promise<ProductCatalog> {
  const query = category ? `?category=${category}` : "";
  const { data } = await apiRequest<ProductCatalog>(`/catalog/products${query}`);
  return data;
}
