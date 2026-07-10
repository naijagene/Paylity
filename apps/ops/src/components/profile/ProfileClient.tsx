"use client";

import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import { useOperatorAuth } from "@/lib/ops/OperatorAuthProvider";

export function ProfileClient() {
  const { status, lock, isAuthenticated } = useOperatorAuth();

  const sessionLabel =
    status === "authenticated"
      ? "Authenticated"
      : status === "session_expired"
        ? "Expired"
        : status === "validating"
          ? "Validating"
          : "Locked";

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
          <h2 className="font-display text-lg font-extrabold text-dark">Operator Session</h2>
          <p className="mt-2 text-sm text-muted">
            {isAuthenticated
              ? "Operator session authenticated."
              : "Operator session is not authenticated."}
          </p>
          <dl className="mt-4 space-y-3 text-sm">
            <div className="flex justify-between gap-4">
              <dt className="text-muted">Session status</dt>
              <dd className="font-semibold text-dark">{sessionLabel}</dd>
            </div>
            <div className="flex justify-between gap-4">
              <dt className="text-muted">Access verification</dt>
              <dd className="font-semibold text-dark">Server validated</dd>
            </div>
          </dl>
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

        <div className="flex flex-col gap-3 sm:flex-row">
          <Button type="button" variant="secondary" onClick={lock}>
            Logout
          </Button>
          <Button type="button" variant="outline" onClick={lock}>
            Lock Console
          </Button>
        </div>
      </div>
    </PageContainer>
  );
}
