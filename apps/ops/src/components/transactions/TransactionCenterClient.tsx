"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import {
  createOpsNote,
  getReceiptDownloadUrl,
  retryOpsFulfillment,
  searchOpsTransactions,
  type OpsTransactionListItem,
} from "@/lib/api/ops";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { formatNaira } from "@/lib/checkout/formatNaira";
import {
  getFulfillmentBadgeLabel,
  getFulfillmentBadgeVariant,
  getPaymentBadgeLabel,
  getPaymentBadgeVariant,
  PRODUCT_LABELS,
} from "@/lib/transaction/display";

type Filters = {
  reference: string;
  phone: string;
  email: string;
  status: string;
  product_type: string;
  date_from: string;
  date_to: string;
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

export function TransactionCenterClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [filters, setFilters] = useState<Filters>({
    reference: searchParams.get("reference") ?? "",
    phone: searchParams.get("phone") ?? "",
    email: "",
    status: searchParams.get("status") ?? "",
    product_type: searchParams.get("product_type") ?? "",
    date_from: searchParams.get("date_from") ?? "",
    date_to: searchParams.get("date_to") ?? "",
  });
  const [items, setItems] = useState<OpsTransactionListItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [noteReference, setNoteReference] = useState<string | null>(null);
  const [noteBody, setNoteBody] = useState("");
  const [savingNote, setSavingNote] = useState(false);
  const initialFilters = useRef(filters);

  const loadTransactions = useCallback(async (nextFilters: Filters) => {
    try {
      const result = await searchOpsTransactions({
        reference: nextFilters.reference || undefined,
        phone: nextFilters.phone || undefined,
        status: nextFilters.status || undefined,
        product_type: nextFilters.product_type || undefined,
        date_from: nextFilters.date_from || undefined,
        date_to: nextFilters.date_to || undefined,
        per_page: 50,
      });

      setItems(result.items);
      setError(null);
    } catch (err) {
      if (err instanceof ApiOfflineError) {
        setError("Network unavailable. Check the API server and try again.");
      } else if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Unable to load transactions.");
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    const nextFilters = initialFilters.current;

    searchOpsTransactions({
      reference: nextFilters.reference || undefined,
      phone: nextFilters.phone || undefined,
      status: nextFilters.status || undefined,
      product_type: nextFilters.product_type || undefined,
      date_from: nextFilters.date_from || undefined,
      date_to: nextFilters.date_to || undefined,
      per_page: 50,
    })
      .then((result) => {
        if (!cancelled) {
          setItems(result.items);
          setError(null);
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
          setError("Unable to load transactions.");
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
    setLoading(true);
    void loadTransactions(filters);
  };

  const handleRetry = async (reference: string) => {
    if (!window.confirm(`Retry fulfillment for ${reference}?`)) {
      return;
    }

    try {
      setLoading(true);
      await retryOpsFulfillment(reference);
      await loadTransactions(filters);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Retry failed.");
    }
  };

  const handleSaveNote = async () => {
    if (!noteReference || !noteBody.trim()) {
      return;
    }

    setSavingNote(true);

    try {
      await createOpsNote(noteReference, noteBody.trim());
      setNoteBody("");
      setNoteReference(null);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to save note.");
    } finally {
      setSavingNote(false);
    }
  };

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-7xl space-y-6">
        <header>
          <h1 className="font-display text-3xl font-extrabold text-dark">Transaction Center</h1>
          <p className="mt-2 text-sm text-muted">
            Search, review, and action transactions during soft launch.
          </p>
        </header>

        <form
          onSubmit={handleSearch}
          className="grid gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm md:grid-cols-2 xl:grid-cols-3"
        >
          {(
            [
              ["reference", "Reference", "PYL-..."],
              ["phone", "Phone", "080..."],
              ["email", "Email", "buyer@example.com"],
            ] as const
          ).map(([key, label, placeholder]) => (
            <label key={key} className="block text-sm">
              <span className="font-semibold text-dark">{label}</span>
              <input
                value={filters[key]}
                onChange={(event) =>
                  setFilters((current) => ({ ...current, [key]: event.target.value }))
                }
                className="mt-2 w-full rounded-2xl border border-border px-4 py-3 outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
                placeholder={placeholder}
                disabled={key === "email"}
              />
              {key === "email" ? (
                <span className="mt-1 block text-xs text-muted">
                  Email filtering will be enabled when the ops API adds support.
                </span>
              ) : null}
            </label>
          ))}

          <label className="block text-sm">
            <span className="font-semibold text-dark">Status</span>
            <select
              value={filters.status}
              onChange={(event) =>
                setFilters((current) => ({ ...current, status: event.target.value }))
              }
              className="mt-2 w-full rounded-2xl border border-border px-4 py-3 outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
            >
              {STATUS_OPTIONS.map((option) => (
                <option key={option || "all"} value={option}>
                  {option || "All statuses"}
                </option>
              ))}
            </select>
          </label>

          <label className="block text-sm">
            <span className="font-semibold text-dark">Product</span>
            <select
              value={filters.product_type}
              onChange={(event) =>
                setFilters((current) => ({ ...current, product_type: event.target.value }))
              }
              className="mt-2 w-full rounded-2xl border border-border px-4 py-3 outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
            >
              {PRODUCT_OPTIONS.map((option) => (
                <option key={option || "all"} value={option}>
                  {option ? (PRODUCT_LABELS[option] ?? option) : "All products"}
                </option>
              ))}
            </select>
          </label>

          <label className="block text-sm">
            <span className="font-semibold text-dark">From</span>
            <input
              type="date"
              value={filters.date_from}
              onChange={(event) =>
                setFilters((current) => ({ ...current, date_from: event.target.value }))
              }
              className="mt-2 w-full rounded-2xl border border-border px-4 py-3 outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
            />
          </label>

          <label className="block text-sm">
            <span className="font-semibold text-dark">To</span>
            <input
              type="date"
              value={filters.date_to}
              onChange={(event) =>
                setFilters((current) => ({ ...current, date_to: event.target.value }))
              }
              className="mt-2 w-full rounded-2xl border border-border px-4 py-3 outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
            />
          </label>

          <div className="md:col-span-2 xl:col-span-3">
            <Button type="submit" disabled={loading}>
              {loading ? "Searching…" : "Search Transactions"}
            </Button>
          </div>
        </form>

        {error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {error}
          </p>
        ) : null}

        <div className="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
          <div className="overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="border-b border-dark/5 bg-dark/[0.02] text-xs uppercase tracking-wide text-muted">
                <tr>
                  <th className="px-4 py-3">Reference</th>
                  <th className="px-4 py-3">Customer</th>
                  <th className="px-4 py-3">Product</th>
                  <th className="px-4 py-3">Amount</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Created</th>
                  <th className="px-4 py-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {items.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="px-4 py-8 text-center text-muted">
                      {loading ? "Loading transactions…" : "No transactions found."}
                    </td>
                  </tr>
                ) : (
                  items.map((transaction) => (
                    <tr key={transaction.reference} className="border-b border-dark/5 last:border-b-0">
                      <td className="px-4 py-3 font-mono text-xs font-semibold">
                        <Link
                          href={`/transactions/${encodeURIComponent(transaction.reference)}`}
                          className="text-success hover:underline"
                        >
                          {transaction.reference}
                        </Link>
                      </td>
                      <td className="px-4 py-3">{transaction.customer_phone || "—"}</td>
                      <td className="px-4 py-3">
                        {PRODUCT_LABELS[transaction.product_type] ?? transaction.product_type}
                      </td>
                      <td className="px-4 py-3">{formatNaira(transaction.payable_amount)}</td>
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
                      <td className="px-4 py-3 text-muted">
                        {transaction.created_at
                          ? new Date(transaction.created_at).toLocaleString("en-NG")
                          : "—"}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex min-w-[14rem] flex-wrap gap-2">
                          <Button
                            type="button"
                            variant="outline"
                            className="min-h-9 px-3 py-2 text-xs"
                            onClick={() =>
                              router.push(
                                `/transactions/${encodeURIComponent(transaction.reference)}`,
                              )
                            }
                          >
                            View
                          </Button>
                          {(transaction.status === "failed" ||
                            transaction.status === "payment_success") && (
                            <Button
                              type="button"
                              variant="secondary"
                              className="min-h-9 px-3 py-2 text-xs"
                              onClick={() => void handleRetry(transaction.reference)}
                            >
                              Retry
                            </Button>
                          )}
                          <Button
                            type="button"
                            variant="outline"
                            className="min-h-9 px-3 py-2 text-xs"
                            onClick={() =>
                              window.open(
                                getReceiptDownloadUrl(transaction.reference),
                                "_blank",
                              )
                            }
                          >
                            Receipt
                          </Button>
                          <Button
                            type="button"
                            variant="outline"
                            className="min-h-9 px-3 py-2 text-xs"
                            onClick={() => setNoteReference(transaction.reference)}
                          >
                            Note
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>

        {noteReference ? (
          <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
            <h2 className="font-display text-lg font-extrabold text-dark">
              Add Note — {noteReference}
            </h2>
            <textarea
              value={noteBody}
              onChange={(event) => setNoteBody(event.target.value)}
              className="mt-4 min-h-28 w-full rounded-2xl border border-border px-4 py-3 text-sm outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
              placeholder="Add an internal operator note"
            />
            <div className="mt-4 flex gap-3">
              <Button type="button" onClick={() => void handleSaveNote()} disabled={savingNote}>
                {savingNote ? "Saving…" : "Save Note"}
              </Button>
              <Button type="button" variant="outline" onClick={() => setNoteReference(null)}>
                Cancel
              </Button>
            </div>
          </section>
        ) : null}
      </div>
    </PageContainer>
  );
}
