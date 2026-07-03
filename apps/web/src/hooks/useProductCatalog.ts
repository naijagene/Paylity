"use client";

import { useEffect, useState } from "react";
import { fetchProductCatalog, type ProductCatalog } from "@/lib/api/catalog";
import { ApiOfflineError } from "@/lib/api/client";

export function useProductCatalog() {
  const [catalog, setCatalog] = useState<ProductCatalog | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    fetchProductCatalog()
      .then((data) => {
        if (!cancelled) {
          setCatalog(data);
          setError(null);
        }
      })
      .catch((err) => {
        if (cancelled) {
          return;
        }

        if (err instanceof ApiOfflineError) {
          setError(
            "Product catalog is unavailable. Please refresh the page and try again.",
          );
        } else if (err instanceof Error) {
          setError(err.message);
        } else {
          setError("Unable to load product catalog.");
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return {
    catalog,
    loading,
    error,
  };
}
