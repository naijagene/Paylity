"use client";

import { useState } from "react";
import Link from "next/link";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { AppFooter } from "@/components/system/AppFooter";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import {
  fetchTransactionHistory,
  type TransactionHistoryItem,
} from "@/lib/api/history";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { formatNaira } from "@/lib/checkout/formatNaira";
import {
  getFulfillmentBadgeLabel,
  getFulfillmentBadgeVariant,
  getPaymentBadgeLabel,
  getPaymentBadgeVariant,
} from "@/lib/transaction/display";

const STATUS_GROUPS = [
  { value: "", label: "All statuses" },
  { value: "delivered", label: "Delivered" },
  { value: "processing", label: "Processing" },
  { value: "failed", label: "Failed" },
];

const PRODUCT_OPTIONS = [
  { value: "", label: "All products" },
  { value: "airtime", label: "Airtime" },
  { value: "data", label: "Data" },
  { value: "electricity", label: "Electricity" },
];

export function TransactionHistoryClient() {
  const [phone, setPhone] = useState("");
  const [statusGroup, setStatusGroup] = useState("");
  const [productType, setProductType] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");
  const [items, setItems] = useState<TransactionHistoryItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [searched, setSearched] = useState(false);

  const handleSearch = async (event: React.FormEvent) => {
    event.preventDefault();

    if (!phone.trim()) {
      setError("Enter the phone number used during checkout.");
      return;
    }

    setLoading(true);
    setError(null);
    setSearched(true);

    try {
      const result = await fetchTransactionHistory({
        phone: phone.trim(),
        status_group: statusGroup || undefined,
        product_type: productType || undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      });

      setItems(result.items);
    } catch (err) {
      if (err instanceof ApiOfflineError) {
        setError("Network unavailable. Check your connection and try again.");
      } else if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Unable to load transaction history.");
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <PageContainer className="py-8 sm:py-12">
      <div className="animate-fade-in mx-auto w-full space-y-6">
        <header className="border-b border-border pb-5">
          <PaylityLogo size="md" href="/" />
        </header>

        <section>
          <h1 className="font-display text-3xl font-extrabold text-dark">
            Transaction History
          </h1>
          <p className="mt-2 text-sm text-muted">
            Look up your recent PAYLITY purchases using the phone number from
            checkout.
          </p>
        </section>

        <form
          onSubmit={(event) => void handleSearch(event)}
          className="grid gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm sm:grid-cols-2"
        >
          <label className="space-y-2 sm:col-span-2">
            <span className="text-sm font-semibold text-dark">Phone number</span>
            <input
              type="tel"
              value={phone}
              onChange={(event) => setPhone(event.target.value)}
              placeholder="08031234567"
              className="w-full rounded-xl border border-border px-4 py-3 text-sm"
            />
          </label>

          <label className="space-y-2">
            <span className="text-sm font-semibold text-dark">Status</span>
            <select
              value={statusGroup}
              onChange={(event) => setStatusGroup(event.target.value)}
              className="w-full rounded-xl border border-border px-4 py-3 text-sm"
            >
              {STATUS_GROUPS.map((option) => (
                <option key={option.value || "all"} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label className="space-y-2">
            <span className="text-sm font-semibold text-dark">Product</span>
            <select
              value={productType}
              onChange={(event) => setProductType(event.target.value)}
              className="w-full rounded-xl border border-border px-4 py-3 text-sm"
            >
              {PRODUCT_OPTIONS.map((option) => (
                <option key={option.value || "all"} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label className="space-y-2">
            <span className="text-sm font-semibold text-dark">From</span>
            <input
              type="date"
              value={dateFrom}
              onChange={(event) => setDateFrom(event.target.value)}
              className="w-full rounded-xl border border-border px-4 py-3 text-sm"
            />
          </label>

          <label className="space-y-2">
            <span className="text-sm font-semibold text-dark">To</span>
            <input
              type="date"
              value={dateTo}
              onChange={(event) => setDateTo(event.target.value)}
              className="w-full rounded-xl border border-border px-4 py-3 text-sm"
            />
          </label>

          <div className="sm:col-span-2">
            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? "Searching…" : "Search Transactions"}
            </Button>
          </div>
        </form>

        {error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {error}
          </p>
        ) : null}

        {searched && !loading && items.length === 0 ? (
          <p className="rounded-2xl border border-border bg-card px-4 py-6 text-center text-sm text-muted">
            No transactions matched your filters.
          </p>
        ) : null}

        <div className="space-y-3">
          {items.map((item) => (
            <Link
              key={item.reference}
              href={`/transaction/${encodeURIComponent(item.reference)}`}
              className="block rounded-2xl border border-border bg-card p-5 shadow-sm transition hover:border-success/40"
            >
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <p className="font-mono text-sm font-bold text-dark">
                    {item.reference}
                  </p>
                  <p className="mt-1 text-sm text-muted">
                    {item.product_label} · {formatNaira(item.payable_amount)}
                  </p>
                  {item.created_at ? (
                    <p className="mt-1 text-xs text-muted">
                      {new Date(item.created_at).toLocaleString("en-NG")}
                    </p>
                  ) : null}
                </div>
                <div className="flex flex-wrap gap-2">
                  <StatusBadge
                    label={getPaymentBadgeLabel(item.status)}
                    variant={getPaymentBadgeVariant(item.status)}
                  />
                  <StatusBadge
                    label={getFulfillmentBadgeLabel(item.status)}
                    variant={getFulfillmentBadgeVariant(item.status)}
                  />
                </div>
              </div>
            </Link>
          ))}
        </div>
      </div>

      <AppFooter className="mt-8" />
    </PageContainer>
  );
}
