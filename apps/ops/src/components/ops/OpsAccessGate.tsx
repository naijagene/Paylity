"use client";

import { useState, type ReactNode } from "react";
import { Button } from "@/components/Button";
import { OpsShell } from "@/components/layout/OpsShell";
import { useOperatorAuth } from "@/lib/ops/OperatorAuthProvider";
import { normalizeOperatorKeyInput } from "@/lib/ops/operatorSession";

type OpsAccessGateProps = {
  children: ReactNode;
};

function AccessGateScreen({
  title,
  description,
  error,
  submitLabel,
  submitting,
  onSubmit,
}: {
  title: string;
  description: string;
  error: string | null;
  submitLabel: string;
  submitting: boolean;
  onSubmit: (key: string) => Promise<void>;
}) {
  const [accessKey, setAccessKey] = useState("");

  const handleSubmit = async () => {
    await onSubmit(accessKey);
    setAccessKey("");
  };

  return (
    <div className="flex min-h-full flex-1 items-center justify-center px-4 py-16">
      <div className="w-full max-w-md rounded-3xl border border-border bg-card p-6 shadow-sm sm:p-8">
        <p className="text-sm font-semibold uppercase tracking-wide text-success">
          Operator Access
        </p>
        <h1 className="mt-2 font-display text-2xl font-extrabold tracking-tight text-dark">
          {title}
        </h1>
        <p className="mt-3 text-sm text-muted">{description}</p>

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
            disabled={submitting}
          />
        </label>

        {error ? (
          <p className="mt-3 text-sm text-error" role="alert">
            {error}
          </p>
        ) : null}

        <Button
          type="button"
          className="mt-6 w-full"
          onClick={() => void handleSubmit()}
          disabled={submitting}
        >
          {submitting ? "Verifying access…" : submitLabel}
        </Button>
      </div>
    </div>
  );
}

function ValidatingScreen() {
  return (
    <div className="flex min-h-full flex-1 items-center justify-center px-4 py-16">
      <div className="text-center">
        <div className="mx-auto h-10 w-10 animate-spin rounded-full border-4 border-success/20 border-t-success" />
        <p className="mt-4 text-sm font-semibold text-dark">Verifying access…</p>
        <p className="mt-2 text-sm text-muted">
          Validating your operator session with the PAYLITY API.
        </p>
      </div>
    </div>
  );
}

export function OpsAccessGate({ children }: OpsAccessGateProps) {
  const { status, error, unlock, lock, isAuthenticated } = useOperatorAuth();

  if (status === "validating") {
    return <ValidatingScreen />;
  }

  if (isAuthenticated) {
    return <OpsShell onLock={lock}>{children}</OpsShell>;
  }

  if (status === "session_expired") {
    return (
      <AccessGateScreen
        title="Session Expired"
        description="Your operator session is no longer valid. Enter your access key to unlock the console again."
        error={error}
        submitLabel="Unlock Console"
        submitting={false}
        onSubmit={unlock}
      />
    );
  }

  if (status === "api_unavailable") {
    return (
      <AccessGateScreen
        title="API Unavailable"
        description="The PAYLITY API could not be reached. Check your connection and try again."
        error={error}
        submitLabel="Retry Verification"
        submitting={false}
        onSubmit={unlock}
      />
    );
  }

  return (
    <AccessGateScreen
      title="PAYLITY Operations Console"
      description="Enter your operator access key to unlock the minimum viable operations console."
      error={
        error ??
        (status === "invalid_key" ? "Invalid operator access key." : null)
      }
      submitLabel="Unlock Console"
      submitting={false}
      onSubmit={async (rawKey) => {
        const key = normalizeOperatorKeyInput(rawKey);

        if (!key) {
          await unlock("");
          return;
        }

        await unlock(key);
      }}
    />
  );
}
