import { Suspense } from "react";
import { PlatformClient } from "@/components/platform/PlatformClient";

export default function PlatformPage() {
  return (
    <Suspense fallback={<p className="px-4 py-8 text-sm text-muted">Loading platform settings…</p>}>
      <PlatformClient />
    </Suspense>
  );
}
