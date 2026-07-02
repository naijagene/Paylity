"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import {
  fetchOpsSummary,
  searchOpsTransactions,
  type OpsSummary,
  type OpsTransactionListItem,
} from "@/lib/api/ops";
import {
  getFulfillmentBadgeLabel,
  getFulfillmentBadgeVariant,
  getPaymentBadgeLabel,
  getPaymentBadgeVariant,
  PRODUCT_LABELS,
} from "@/lib/transaction/display";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { formatNaira } from "@/lib/checkout/formatNaira";

type SearchFilters = {
  reference: string;
  phone: string;
  status: string;
  product_type: string;
};

const STATUS_OPTIONS = [
  "",
  "payment_pending",
  "payment_success",
  "payment_failed",
  "fulfillment_pending",
  "fulfilled",
  "failed",
];

const PRODUCT_OPTIONS = ["", "airtime", "data", "electricity"];

function SummaryCard({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-3xl border border-dark/5 bg-white p-4 shadow-sm">
      <p className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
        {label}
      </p>
      <p className="mt-2 text-2xl font-black text-foreground">{value}</p>
    </div>
  );
}

export function OpsDashboardClient() {
  const [filters, setFilters] = useState<SearchFilters>({
    reference: "",
    phone: "",
    status: "",
    product_type: "",
  });
  const [summary, setSummary] = useState<OpsSummary | null>(null);
  const [transactions, setTransactions] = useState<OpsTransactionListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const loadDashboard = useCallback(async (searchFilters: SearchFilters) => {
    setLoading(true);
    setError(null);

    try {
      const [summaryData, searchResult] = await Promise.all([
        fetchOpsSummary(),
        searchOpsTransactions({
          reference: searchFilters.reference || undefined,
          phone: searchFilters.phone || undefined,
          status: searchFilters.status || undefined,
          product_type: searchFilters.product_type || undefined,
          per_page: 20,
        }),
      ]);

      setSummary(summaryData);
      setTransactions(searchResult.items);
    } catch (err) {
      if (err instanceof ApiOfflineError) {
        setError("Network unavailable. Check the API server and try again.");
      } else if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Unable to load operations dashboard.");
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;

    Promise.all([
      fetchOpsSummary(),
      searchOpsTransactions({ per_page: 20 }),
    ])
      .then(([summaryData, searchResult]) => {
        if (!cancelled) {
          setSummary(summaryData);
          setTransactions(searchResult.items);
        }
      })
      .catch((err) => {
        if (cancelled) {
          return;
        }

        if (err instanceof ApiOfflineError) {
          setError("Network unavailable. Check the API server and try again.");
        } else if (err instanceof ApiError) {
          setError(err.message);
        } else {
          setError("Unable to load operations dashboard.");
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

  const handleSearch = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    void loadDashboard(filters);
  };

  return (
    <PageContainer className="py-8">
      <div className="mx-auto w-full max-w-6xl space-y-8">
        <header>
          <h1 className="text-3xl font-black tracking-tight text-foreground">
            Operations Dashboard
          </h1>
          <p className="mt-2 text-sm text-foreground/60">
            Search transactions by reference, phone, status, or product type.
          </p>
        </header>

        {summary ? (
          <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <SummaryCard
              label="Transactions Today"
              value={summary.total_transactions_today}
            />
            <SummaryCard
              label="Successful Payments Today"
              value={summary.successful_payments_today}
            />
            <SummaryCard label="Fulfilled Today" value={summary.fulfilled_today} />
            <SummaryCard label="Failed Today" value={summary.failed_today} />
            <SummaryCard
              label="Pending Fulfillment"
              value={summary.pending_fulfillment}
            />
            <SummaryCard
              label="Convenience Fees Today"
              value={formatNaira(summary.total_convenience_fees_today)}
            />
          </section>
        ) : null}

        <section className="rounded-3xl border border-dark/5 bg-white p-5 shadow-sm">
          <form
            className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            onSubmit={handleSearch}
          >
            <label className="block text-sm">
              <span className="font-semibold text-foreground">Reference</span>
              <input
                value={filters.reference}
                onChange={(event) =>
                  setFilters((current) => ({
                    ...current,
                    reference: event.target.value,
                  }))
                }
                className="mt-2 w-full rounded-2xl border border-dark/10 px-4 py-3 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                placeholder="PYL-..."
              />
            </label>
            <label className="block text-sm">
              <span className="font-semibold text-foreground">Phone</span>
              <input
                value={filters.phone}
                onChange={(event) =>
                  setFilters((current) => ({
                    ...current,
                    phone: event.target.value,
                  }))
                }
                className="mt-2 w-full rounded-2xl border border-dark/10 px-4 py-3 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                placeholder="080..."
              />
            </label>
            <label className="block text-sm">
              <span className="font-semibold text-foreground">Status</span>
              <select
                value={filters.status}
                onChange={(event) =>
                  setFilters((current) => ({
                    ...current,
                    status: event.target.value,
                  }))
                }
                className="mt-2 w-full rounded-2xl border border-dark/10 px-4 py-3 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              >
                {STATUS_OPTIONS.map((option) => (
                  <option key={option || "all"} value={option}>
                    {option || "All statuses"}
                  </option>
                ))}
              </select>
            </label>
            <label className="block text-sm">
              <span className="font-semibold text-foreground">Product</span>
              <select
                value={filters.product_type}
                onChange={(event) =>
                  setFilters((current) => ({
                    ...current,
                    product_type: event.target.value,
                  }))
                }
                className="mt-2 w-full rounded-2xl border border-dark/10 px-4 py-3 outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              >
                {PRODUCT_OPTIONS.map((option) => (
                  <option key={option || "all"} value={option}>
                    {option
                      ? (PRODUCT_LABELS[option] ?? option)
                      : "All products"}
                  </option>
                ))}
              </select>
            </label>
            <div className="md:col-span-2 xl:col-span-4">
              <Button type="submit" disabled={loading}>
                {loading ? "Searching..." : "Search Transactions"}
              </Button>
            </div>
          </form>
        </section>

        {error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {error}
          </p>
        ) : null}

        <section className="overflow-hidden rounded-3xl border border-dark/5 bg-white shadow-sm">
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-dark/5 bg-dark/[0.02] text-xs uppercase tracking-wide text-foreground/45">
                <tr>
                  <th className="px-4 py-3">Reference</th>
                  <th className="px-4 py-3">Product</th>
                  <th className="px-4 py-3">Phone</th>
                  <th className="px-4 py-3">Amount</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Created</th>
                </tr>
              </thead>
              <tbody>
                {transactions.length === 0 ? (
                  <tr>
                    <td
                      colSpan={6}
                      className="px-4 py-8 text-center text-foreground/50"
                    >
                      {loading ? "Loading transactions..." : "No transactions found."}
                    </td>
                  </tr>
                ) : (
                  transactions.map((transaction) => (
                    <tr
                      key={transaction.reference}
                      className="border-b border-dark/5 last:border-b-0"
                    >
                      <td className="px-4 py-3 font-mono text-xs">
                        <Link
                          href={`/ops/transactions/${encodeURIComponent(transaction.reference)}`}
                          className="font-semibold text-primary hover:underline"
                        >
                          {transaction.reference}
                        </Link>
                      </td>
                      <td className="px-4 py-3">
                        {PRODUCT_LABELS[transaction.product_type] ??
                          transaction.product_type}
                      </td>
                      <td className="px-4 py-3">{transaction.customer_phone}</td>
                      <td className="px-4 py-3">
                        {formatNaira(transaction.payable_amount)}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex flex-wrap gap-2">
                          <StatusBadge
                            label={getPaymentBadgeLabel(transaction.status)}
                            variant={getPaymentBadgeVariant(transaction.status)}
                          />
                          <StatusBadge
                            label={getFulfillmentBadgeLabel(transaction.status)}
                            variant={getFulfillmentBadgeVariant(transaction.status)}
                          />
                        </div>
                      </td>
                      <td className="px-4 py-3 text-foreground/60">
                        {transaction.created_at
                          ? new Date(transaction.created_at).toLocaleString("en-NG")
                          : "—"}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </PageContainer>
  );
}
