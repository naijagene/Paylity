"use client";

import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { CopyButton } from "@/components/ui/CopyButton";
import { clearOperatorKey, getOperatorKey } from "@/lib/ops/operatorKey";

export function ProfileClient() {
  const operatorKey = typeof window !== "undefined" ? getOperatorKey() : null;
  const maskedKey = operatorKey
    ? `${operatorKey.slice(0, 4)}${"•".repeat(Math.max(operatorKey.length - 8, 4))}${operatorKey.slice(-4)}`
    : "Not set";

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-3xl space-y-6">
        <header>
          <h1 className="font-display text-3xl font-extrabold text-dark">Admin Profile</h1>
          <p className="mt-2 text-sm text-muted">
            Operator session details for the PAYLITY soft launch console.
          </p>
        </header>

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="font-display text-lg font-extrabold text-dark">Profile</h2>
          <dl className="mt-4 space-y-3 text-sm">
            <div className="flex justify-between gap-4">
              <dt className="text-muted">Role</dt>
              <dd className="font-semibold text-dark">Operator</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-muted">Console</dt>
              <dd className="font-semibold text-dark">PAYLITY MVOC</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-muted">RBAC</dt>
              <dd className="font-semibold text-dark">Coming soon</dd>
            </div>
          </dl>
        </section>

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="font-display text-lg font-extrabold text-dark">Operator Key</h2>
          <p className="mt-2 text-sm text-muted">
            Your operator key is stored in this browser session only.
          </p>
          <p className="mt-4 font-mono text-sm font-semibold text-dark">{maskedKey}</p>
          {operatorKey ? (
            <div className="mt-4">
              <CopyButton value={operatorKey} label="Copy Key" />
            </div>
          ) : null}
        </section>

        <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
          <h2 className="font-display text-lg font-extrabold text-dark">Change Password</h2>
          <p className="mt-2 text-sm text-muted">
            Password-based operator accounts will be introduced with future RBAC support.
          </p>
          <Button type="button" variant="outline" className="mt-4" disabled>
            Change Password
          </Button>
        </section>

        <Button
          type="button"
          variant="secondary"
          onClick={() => {
            clearOperatorKey();
            window.location.reload();
          }}
        >
          Logout
        </Button>
      </div>
    </PageContainer>
  );
}
