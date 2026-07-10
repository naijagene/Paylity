"use client";

import { ToastProvider } from "@/components/ui/ToastProvider";
import { OperatorAuthProvider } from "@/lib/ops/OperatorAuthProvider";

export function Providers({ children }: { children: React.ReactNode }) {
  return (
    <ToastProvider>
      <OperatorAuthProvider>{children}</OperatorAuthProvider>
    </ToastProvider>
  );
}
