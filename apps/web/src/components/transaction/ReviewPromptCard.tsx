"use client";

import { useState } from "react";
import { Button } from "@/components/Button";

type ReviewPromptCardProps = {
  reference: string;
  onSubmit: (rating: number, comment?: string) => Promise<void>;
};

export function ReviewPromptCard({ reference, onSubmit }: ReviewPromptCardProps) {
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState("");
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function handleSubmit() {
    if (rating < 1) {
      setError("Select a star rating before submitting.");
      return;
    }

    setLoading(true);
    setError(null);

    try {
      await onSubmit(rating, comment.trim() || undefined);
      setSubmitted(true);
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : "Unable to submit review.");
    } finally {
      setLoading(false);
    }
  }

  if (submitted) {
    return (
      <section className="rounded-2xl border border-success/20 bg-success/5 p-5 shadow-sm">
        <h2 className="text-lg font-bold text-success">Thank you for your review</h2>
        <p className="mt-2 text-sm text-muted">Your feedback helps PAYLITY improve the launch experience.</p>
      </section>
    );
  }

  return (
    <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
      <h2 className="text-lg font-bold text-dark">How was your experience?</h2>
      <p className="mt-1 text-sm text-muted">Rate your {reference} delivery experience.</p>
      <div className="mt-4 flex gap-2">
        {[1, 2, 3, 4, 5].map((value) => (
          <button
            key={value}
            type="button"
            aria-label={`Rate ${value} stars`}
            className={`rounded-full px-3 py-2 text-sm font-semibold ${rating >= value ? "bg-amber-400 text-dark" : "bg-slate-100 text-muted"}`}
            onClick={() => setRating(value)}
          >
            {value}★
          </button>
        ))}
      </div>
      <textarea
        value={comment}
        onChange={(event) => setComment(event.target.value)}
        placeholder="Optional comment"
        className="mt-4 min-h-24 w-full rounded-xl border border-border px-4 py-3 text-sm"
      />
      {error ? <p className="mt-2 text-sm text-danger">{error}</p> : null}
      <Button type="button" className="mt-4 w-full" disabled={loading} onClick={() => void handleSubmit()}>
        {loading ? "Submitting…" : "Submit Review"}
      </Button>
    </section>
  );
}
