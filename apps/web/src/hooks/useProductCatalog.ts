"use client";

import { useCallback, useEffect, useState } from "react";
import { fetchProductCatalog, type ProductCatalog } from "@/lib/api/catalog";
import { ApiOfflineError } from "@/lib/api/client";
import type { ProductType } from "@/lib/checkout/types";

const CATALOG_ERROR_MESSAGE =
  "Product catalog is unavailable. Please refresh the page and try again.";

function mergeCatalog(
  previous: ProductCatalog | null,
  next: ProductCatalog,
): ProductCatalog {
  return {
    categories: next.categories ?? previous?.categories ?? [],
    provider: next.provider ?? previous?.provider ?? "vtpass",
    catalog_meta: next.catalog_meta ?? previous?.catalog_meta,
    airtime_networks: next.airtime_networks ?? previous?.airtime_networks,
    data_services: next.data_services ?? previous?.data_services,
    electricity_discos: next.electricity_discos ?? previous?.electricity_discos,
  };
}

export function useProductCatalog(product: ProductType) {
  const [catalog, setCatalog] = useState<ProductCatalog | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadCatalog = useCallback(async (category: ProductType, cancelled: () => boolean) => {
    setLoading(true);
    setError(null);

    try {
      const data = await fetchProductCatalog(category);

      if (!cancelled()) {
        setCatalog((previous) => mergeCatalog(previous, data));
        setError(null);
      }
    } catch (err) {
      if (cancelled()) {
        return;
      }

      if (err instanceof ApiOfflineError) {
        setError(CATALOG_ERROR_MESSAGE);
      } else if (err instanceof Error) {
        setError(err.message);
      } else {
        setError("Unable to load product catalog.");
      }
    } finally {
      if (!cancelled()) {
        setLoading(false);
      }
    }
  }, []);

  useEffect(() => {
    let cancelled = false;

    void loadCatalog(product, () => cancelled);

    return () => {
      cancelled = true;
    };
  }, [loadCatalog, product]);

  const refetch = useCallback(() => {
    void loadCatalog(product, () => false);
  }, [loadCatalog, product]);

  return {
    catalog,
    loading,
    error,
    refetch,
  };
}
