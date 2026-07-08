"use client";

import { ToastProvider } from "@/components/ui/ToastProvider";
import { IncidentModeBanner } from "@/components/system/IncidentModeBanner";

export function Providers({ children }: { children: React.ReactNode }) {
  return (
    <ToastProvider>
      <IncidentModeBanner />
      {children}
    </ToastProvider>
  );
}
