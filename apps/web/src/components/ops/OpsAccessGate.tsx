"use client";

import { useState, type ReactNode } from "react";
import { Button } from "@/components/Button";
import { PaylityLogo } from "@/components/brand/PaylityLogo";
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
        <div className="h-10 w-10 animate-spin rounded-full border-4 border-primary/20 border-t-primary" />
      </div>
    );
  }

  if (!authenticated) {
    return (
      <div className="flex min-h-full flex-1 items-center justify-center px-4 py-16">
        <div className="w-full max-w-md rounded-3xl border border-dark/5 bg-white p-6 shadow-sm sm:p-8">
          <p className="text-sm font-semibold uppercase tracking-wide text-primary">
            Internal Access
          </p>
          <h1 className="mt-2 text-2xl font-black tracking-tight text-foreground">
            Operations Console
          </h1>
          <p className="mt-3 text-sm text-foreground/60">
            Enter the operator access key to manage transactions and manual
            fulfillment. This area is not for customers.
          </p>

          <label className="mt-6 block text-left">
            <span className="text-sm font-semibold text-foreground">
              Operator Access Key
            </span>
            <input
              type="password"
              value={accessKey}
              onChange={(event) => setAccessKey(event.target.value)}
              className="mt-2 w-full rounded-2xl border border-dark/10 px-4 py-3 text-sm outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20"
              placeholder="Enter access key"
              autoComplete="off"
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
    <OpsConsoleFrame onLock={handleLock} frameKey={authTick}>
      {children}
    </OpsConsoleFrame>
  );
}

function OpsConsoleFrame({
  children,
  onLock,
  frameKey,
}: {
  children: ReactNode;
  onLock: () => void;
  frameKey: number;
}) {
  return (
    <div key={frameKey} className="flex min-h-full flex-1 flex-col">
      <div className="border-b border-amber-200 bg-amber-50 px-4 py-3 text-center text-sm text-amber-900">
        <strong>Internal use only.</strong> Do not share this console or access
        key with customers.
      </div>
      <div className="border-b border-dark/5 bg-white px-4 py-3">
        <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <PaylityLogo size="sm" showText={false} />
            <div>
              <p className="text-xs font-semibold uppercase tracking-wide text-primary">
                PAYLITY Ops
              </p>
              <p className="text-sm font-semibold text-dark">
                Internal Operations Console
              </p>
            </div>
          </div>
          <Button type="button" variant="outline" onClick={onLock}>
            Lock Console
          </Button>
        </div>
      </div>
      <div className="flex-1">{children}</div>
    </div>
  );
}
