import { OpsAccessGate } from "@/components/ops/OpsAccessGate";
import { OpsTransactionDetailClient } from "@/components/ops/OpsTransactionDetailClient";

export default function OpsTransactionPage() {
  return (
    <main className="flex min-h-full flex-1 flex-col">
      <OpsAccessGate>
        <OpsTransactionDetailClient />
      </OpsAccessGate>
    </main>
  );
}
