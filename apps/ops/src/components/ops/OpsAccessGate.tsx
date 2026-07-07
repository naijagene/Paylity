"use client";

import { useState, type ReactNode } from "react";
import { Button } from "@/components/Button";
import { OpsShell } from "@/components/layout/OpsShell";
import {
  clearOperatorKey,
  hasOperatorKey,
  setOperatorKey,
} from "@/lib/ops/operatorKey";

type OpsAccessGateProps = {
  children: ReactNode;
};

export function OpsAccessGate({ children }: OpsAccessGateProps) {
  const isBrowser = typeof window !== "undefined";
  const [authTick, setAuthTick] = useState(0);
  const [accessKey, setAccessKey] = useState("");
  const [error, setError] = useState<string | null>(null);

  const authenticated = isBrowser && hasOperatorKey();

  const handleUnlock = () => {
    if (!accessKey.trim()) {
      setError("Enter the operator access key.");
      return;
    }

    setOperatorKey(accessKey.trim());
    setAccessKey("");
    setError(null);
    setAuthTick((value) => value + 1);
  };

  const handleLock = () => {
    clearOperatorKey();
    setAuthTick((value) => value + 1);
  };

  if (!isBrowser) {
    return (
      <div className="flex min-h-full flex-1 items-center justify-center px-4 py-16">
        <div className="h-10 w-10 animate-spin rounded-full border-4 border-success/20 border-t-success" />
      </div>
    );
  }

  if (!authenticated) {
    return (
      <div className="flex min-h-full flex-1 items-center justify-center px-4 py-16">
        <div className="w-full max-w-md rounded-3xl border border-border bg-card p-6 shadow-sm sm:p-8">
          <p className="text-sm font-semibold uppercase tracking-wide text-success">
            Operator Access
          </p>
          <h1 className="mt-2 font-display text-2xl font-extrabold tracking-tight text-dark">
            PAYLITY Operations Console
          </h1>
          <p className="mt-3 text-sm text-muted">
            Enter your operator access key to unlock the minimum viable operations console.
          </p>

          <label className="mt-6 block text-left">
            <span className="text-sm font-semibold text-dark">Operator Access Key</span>
            <input
              type="password"
              value={accessKey}
              onChange={(event) => setAccessKey(event.target.value)}
              className="mt-2 w-full rounded-2xl border border-border px-4 py-3 text-sm outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
              placeholder="Enter access key"
              autoComplete="off"
              aria-label="Operator access key"
            />
          </label>

          {error ? (
            <p className="mt-3 text-sm text-error" role="alert">
              {error}
            </p>
          ) : null}

          <Button type="button" className="mt-6 w-full" onClick={handleUnlock}>
            Unlock Console
          </Button>
        </div>
      </div>
    );
  }

  return (
    <OpsShell key={authTick} onLock={handleLock}>
      {children}
    </OpsShell>
  );
}
