"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useParams } from "next/navigation";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
import { StatusBadge } from "@/components/transaction/StatusBadge";
import { TransactionReceiptCard } from "@/components/transaction/TransactionReceiptCard";
import { TransactionTimeline } from "@/components/transaction/TransactionTimeline";
import {
  createOpsNote,
  fetchOpsNotes,
  fetchOpsTransaction,
  fulfillOpsTransaction,
  retryOpsFulfillment,
  type OpsNote,
  type OpsTransactionDetail,
} from "@/lib/api/ops";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import {
  getFulfillmentBadgeLabel,
  getFulfillmentBadgeVariant,
  getPaymentBadgeLabel,
  getPaymentBadgeVariant,
  getTimelinePhase,
  PRODUCT_LABELS,
} from "@/lib/transaction/display";

function canManualFulfill(status: string): boolean {
  return status === "payment_success" || status === "failed";
}

export function OpsTransactionDetailClient() {
  const params = useParams<{ reference: string }>();
  const reference = decodeURIComponent(params.reference ?? "");
  const [transaction, setTransaction] = useState<OpsTransactionDetail | null>(
    null,
  );
  const [loading, setLoading] = useState(true);
  const [fulfilling, setFulfilling] = useState(false);
  const [retrying, setRetrying] = useState(false);
  const [notes, setNotes] = useState<OpsNote[]>([]);
  const [noteBody, setNoteBody] = useState("");
  const [savingNote, setSavingNote] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const loadTransaction = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const [data, noteData] = await Promise.all([
        fetchOpsTransaction(reference),
        fetchOpsNotes(reference),
      ]);
      setTransaction(data);
      setNotes(noteData);
    } catch (err) {
      if (err instanceof ApiOfflineError) {
        setError("Network unavailable. Check the API server and try again.");
      } else if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Unable to load transaction details.");
      }
    } finally {
      setLoading(false);
    }
  }, [reference]);

  useEffect(() => {
    let cancelled = false;

    fetchOpsTransaction(reference)
      .then((data) => {
        if (!cancelled) {
          setTransaction(data);
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
          setError("Unable to load transaction details.");
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
  }, [reference]);

  useEffect(() => {
    fetchOpsNotes(reference)
      .then(setNotes)
      .catch(() => {
        // Notes are optional in the UI if the request fails.
      });
  }, [reference, actionMessage]);

  const handleRetry = async () => {
    if (!transaction) {
      return;
    }

    const confirmed = window.confirm(
      "Retry fulfillment for this transaction?",
    );

    if (!confirmed) {
      return;
    }

    setRetrying(true);
    setActionMessage(null);
    setError(null);

    try {
      const result = await retryOpsFulfillment(transaction.reference);
      setActionMessage(result.message);
      await loadTransaction();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Fulfillment retry failed.");
      }
    } finally {
      setRetrying(false);
    }
  };

  const handleAddNote = async () => {
    if (!transaction || !noteBody.trim()) {
      return;
    }

    setSavingNote(true);
    setError(null);

    try {
      await createOpsNote(transaction.reference, noteBody.trim());
      setNoteBody("");
      setNotes(await fetchOpsNotes(transaction.reference));
      setActionMessage("Note added.");
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Unable to save note.");
      }
    } finally {
      setSavingNote(false);
    }
  };

  const handleFulfill = async () => {
    if (!transaction) {
      return;
    }

    const confirmed = window.confirm(
      "Only fulfill after payment_success is confirmed. Proceed with manual VTPass fulfillment?",
    );

    if (!confirmed) {
      return;
    }

    setFulfilling(true);
    setActionMessage(null);
    setError(null);

    try {
      const result = await fulfillOpsTransaction(transaction.reference);
      setActionMessage(result.message);
      await loadTransaction();
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.message);
      } else {
        setError("Manual fulfillment failed.");
      }
    } finally {
      setFulfilling(false);
    }
  };

  if (loading) {
    return (
      <PageContainer className="flex min-h-[40vh] items-center justify-center py-16">
        <div className="h-10 w-10 animate-spin rounded-full border-4 border-success/20 border-t-success" />
      </PageContainer>
    );
  }

  if (error && !transaction) {
    return (
      <PageContainer className="py-16 text-center">
        <p className="text-sm text-error">{error}</p>
        <div className="mt-6 flex justify-center gap-3">
          <Button onClick={() => void loadTransaction()}>Refresh</Button>
          <Button href="/ops" variant="outline">
            Back to Ops
          </Button>
        </div>
      </PageContainer>
    );
  }

  if (!transaction) {
    return null;
  }

  const productLabel =
    PRODUCT_LABELS[transaction.product_type] ?? transaction.product_type;

  return (
    <PageContainer className="py-8">
      <div className="mx-auto w-full max-w-4xl space-y-6">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <PaylityLogo size="sm" href="/ops" />
            <Link
              href="/ops"
              className="mt-3 inline-flex text-sm font-semibold text-success hover:underline"
            >
              ← Back to Ops
            </Link>
            <h1 className="mt-2 font-mono text-2xl font-extrabold text-dark sm:text-3xl">
              {transaction.reference}
            </h1>
            <div className="mt-3 flex flex-wrap gap-2">
              <StatusBadge
                label={getPaymentBadgeLabel(transaction.status)}
                variant={getPaymentBadgeVariant(transaction.status)}
              />
              <StatusBadge
                label={getFulfillmentBadgeLabel(transaction.status)}
                variant={getFulfillmentBadgeVariant(transaction.status)}
              />
            </div>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row">
            <Button variant="outline" onClick={() => void loadTransaction()}>
              Refresh
            </Button>
            {canManualFulfill(transaction.status) ? (
              <Button onClick={() => void handleFulfill()} disabled={fulfilling}>
                {fulfilling ? "Fulfilling..." : "Manual Fulfill"}
              </Button>
            ) : null}
            {transaction.status === "failed" ? (
              <Button
                variant="secondary"
                onClick={() => void handleRetry()}
                disabled={retrying}
              >
                {retrying ? "Retrying..." : "Retry Fulfillment"}
              </Button>
            ) : null}
          </div>
        </div>

        {canManualFulfill(transaction.status) ? (
          <div className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Only fulfill after <strong>payment_success</strong> is confirmed.
            Failed fulfillment retries are allowed from this console.
          </div>
        ) : null}

        {error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {error}
          </p>
        ) : null}

        {actionMessage ? (
          <p className="rounded-2xl border border-success/20 bg-success/5 px-4 py-3 text-sm text-success">
            {actionMessage}
          </p>
        ) : null}

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Fulfillment Diagnostics
          </h2>
          <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-foreground/60">Auto-fulfill attempted</dt>
              <dd className="font-semibold">
                {transaction.auto_fulfill_attempted === true
                  ? "Yes"
                  : transaction.auto_fulfill_attempted === false
                    ? "No"
                    : "Not recorded"}
              </dd>
            </div>
            <div>
              <dt className="text-foreground/60">Auto-fulfill outcome</dt>
              <dd className="font-semibold">
                {transaction.auto_fulfill_outcome || "—"}
              </dd>
            </div>
            <div className="sm:col-span-2">
              <dt className="text-foreground/60">Failure reason</dt>
              <dd className="font-semibold text-error">
                {transaction.failure_reason || "—"}
              </dd>
            </div>
            {transaction.auto_fulfill_reason ? (
              <div className="sm:col-span-2">
                <dt className="text-foreground/60">Auto-fulfill note</dt>
                <dd className="font-semibold">{transaction.auto_fulfill_reason}</dd>
              </div>
            ) : null}
          </dl>
        </section>

        <TransactionReceiptCard
          reference={transaction.reference}
          productLabel={productLabel}
          customerPhone={transaction.customer_phone}
          productAmount={transaction.product_amount}
          convenienceFee={transaction.convenience_fee}
          gatewayFee={transaction.gateway_fee}
          payableAmount={transaction.payable_amount}
          transactionStatus={transaction.status}
          failureReason={transaction.failure_reason}
        />

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Customer Details
          </h2>
          <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-foreground/60">Phone</dt>
              <dd className="font-semibold">{transaction.customer_phone}</dd>
            </div>
            <div>
              <dt className="text-foreground/60">Email</dt>
              <dd className="font-semibold">
                {transaction.customer_email || "—"}
              </dd>
            </div>
            <div>
              <dt className="text-foreground/60">Name</dt>
              <dd className="font-semibold">
                {transaction.customer_name || "—"}
              </dd>
            </div>
            <div>
              <dt className="text-foreground/60">Verified Phone</dt>
              <dd className="font-semibold">
                {transaction.verified_phone ? "Yes" : "No"}
              </dd>
            </div>
          </dl>
        </section>

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Event Timeline
          </h2>
          <div className="space-y-3">
            {(transaction.timeline ?? []).length > 0 ? (
              transaction.timeline?.map((event) => (
                <div
                  key={`${event.event_type}-${event.occurred_at}`}
                  className="rounded-xl border border-border px-4 py-3 text-sm"
                >
                  <p className="font-semibold text-dark">{event.summary}</p>
                  <p className="mt-1 text-xs text-muted">
                    {event.actor} · {event.event_type}
                    {event.occurred_at
                      ? ` · ${new Date(event.occurred_at).toLocaleString("en-NG")}`
                      : ""}
                  </p>
                </div>
              ))
            ) : (
              <p className="text-sm text-muted">No audit events recorded yet.</p>
            )}
          </div>
        </section>

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Retry History
          </h2>
          <div className="space-y-3">
            {(transaction.fulfillment_attempts ?? []).length > 0 ? (
              transaction.fulfillment_attempts?.map((attempt) => (
                <div
                  key={`${attempt.attempt_number}-${attempt.attempted_at}`}
                  className="rounded-xl border border-border px-4 py-3 text-sm"
                >
                  <p className="font-semibold text-dark">
                    Attempt {attempt.attempt_number} · {attempt.outcome}
                  </p>
                  <p className="mt-1 text-xs text-muted">
                    {attempt.actor}
                    {attempt.request_id ? ` · ${attempt.request_id}` : ""}
                    {attempt.duration_ms != null
                      ? ` · ${attempt.duration_ms}ms`
                      : ""}
                  </p>
                  {attempt.failure_reason ? (
                    <p className="mt-2 text-xs text-error">
                      {attempt.failure_reason}
                    </p>
                  ) : null}
                </div>
              ))
            ) : (
              <p className="text-sm text-muted">No fulfillment attempts recorded.</p>
            )}
          </div>
        </section>

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Webhook History
          </h2>
          <div className="space-y-3">
            {(transaction.webhook_history ?? []).length > 0 ? (
              transaction.webhook_history?.map((event) => (
                <div
                  key={event.event_id}
                  className="rounded-xl border border-border px-4 py-3 text-sm"
                >
                  <p className="font-semibold text-dark">
                    {event.provider} · {event.event_type}
                  </p>
                  <p className="mt-1 text-xs text-muted">
                    {event.status}
                    {event.created_at
                      ? ` · ${new Date(event.created_at).toLocaleString("en-NG")}`
                      : ""}
                  </p>
                </div>
              ))
            ) : (
              <p className="text-sm text-muted">No webhook events recorded.</p>
            )}
          </div>
        </section>

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Manual Notes
          </h2>
          <div className="space-y-3">
            {notes.map((note) => (
              <div
                key={note.id}
                className="rounded-xl border border-border px-4 py-3 text-sm"
              >
                <p>{note.body}</p>
                <p className="mt-2 text-xs text-muted">
                  {note.author}
                  {note.created_at
                    ? ` · ${new Date(note.created_at).toLocaleString("en-NG")}`
                    : ""}
                </p>
              </div>
            ))}
          </div>
          <div className="mt-4 space-y-3">
            <textarea
              value={noteBody}
              onChange={(event) => setNoteBody(event.target.value)}
              rows={3}
              placeholder="Add an operator note..."
              className="w-full rounded-xl border border-border px-4 py-3 text-sm"
            />
            <Button
              onClick={() => void handleAddNote()}
              disabled={savingNote || !noteBody.trim()}
            >
              {savingNote ? "Saving..." : "Add Note"}
            </Button>
          </div>
        </section>

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="mb-4 text-xs font-semibold uppercase tracking-wide text-foreground/45">
            Status Timeline
          </h2>
          <TransactionTimeline phase={getTimelinePhase(transaction.status)} />
        </section>

        <details className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <summary className="cursor-pointer text-sm font-semibold text-foreground">
            Raw Payloads
          </summary>
          <div className="mt-4 space-y-4">
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
                Request Payload
              </p>
              <pre className="mt-2 overflow-x-auto rounded-2xl bg-dark/[0.03] p-4 text-xs">
                {JSON.stringify(transaction.request_payload ?? {}, null, 2)}
              </pre>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-foreground/45">
                Response Payload
              </p>
              <pre className="mt-2 overflow-x-auto rounded-2xl bg-dark/[0.03] p-4 text-xs">
                {JSON.stringify(transaction.response_payload ?? {}, null, 2)}
              </pre>
            </div>
            <div className="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <p className="text-foreground/60">IP Address</p>
                <p className="font-mono text-xs">{transaction.ip_address || "—"}</p>
              </div>
              <div>
                <p className="text-foreground/60">User Agent</p>
                <p className="break-all font-mono text-xs">
                  {transaction.user_agent || "—"}
                </p>
              </div>
            </div>
          </div>
        </details>
      </div>
    </PageContainer>
  );
}
