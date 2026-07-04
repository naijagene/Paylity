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
  display_name: string;
  amount: number | null;
  fixed_price: boolean;
  is_popular?: boolean;
  validity_label?: string | null;
  data_size_label?: string | null;
  customer_category?: string | null;
  sort_order?: number | null;
  is_visible?: boolean;
  display_override?: boolean;
};

export type CatalogDataService = {
  service_name: string;
  service_id: string;
  display_name: string;
  network: string;
  variations: CatalogVariation[];
};

export type CatalogMeta = {
  total_variations: number;
  visible_variations: number;
  hidden_variations: number;
};

export type ProductCatalog = {
  categories: CatalogCategory[];
  provider: string;
  catalog_meta?: CatalogMeta;
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
