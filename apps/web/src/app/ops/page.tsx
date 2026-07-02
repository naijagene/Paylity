import { OpsAccessGate } from "@/components/ops/OpsAccessGate";
import { OpsDashboardClient } from "@/components/ops/OpsDashboardClient";

export default function OpsPage() {
  return (
    <main className="flex min-h-full flex-1 flex-col">
      <OpsAccessGate>
        <OpsDashboardClient />
      </OpsAccessGate>
    </main>
  );
}
