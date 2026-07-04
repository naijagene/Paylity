import type { CatalogDataPlan } from "@/lib/checkout/catalogPlans";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { FormField } from "./ValidationMessage";

type DataPlanSelectorProps = {
  network: string;
  selectedPlanId: string;
  plans: CatalogDataPlan[];
  catalogLoading?: boolean;
  catalogError?: string | null;
  onChange: (planId: string) => void;
  error?: string;
};

function PlanBadge({ label }: { label: string }) {
  return (
    <span className="rounded-full bg-dark/[0.06] px-2.5 py-1 text-xs font-semibold text-foreground/70">
      {label}
    </span>
  );
}

export function DataPlanSelector({
  network,
  selectedPlanId,
  plans,
  catalogLoading = false,
  catalogError = null,
  onChange,
  error,
}: DataPlanSelectorProps) {
  return (
    <FormField label="Data plan" htmlFor="data-plan" error={error}>
      {!network ? (
        <p className="rounded-2xl border border-dashed border-dark/10 px-4 py-6 text-center text-sm text-foreground/50">
          Select a network to see available plans
        </p>
      ) : catalogLoading ? (
        <p className="rounded-2xl border border-dashed border-dark/10 px-4 py-6 text-center text-sm text-foreground/50">
          Loading available data plans…
        </p>
      ) : catalogError ? (
        <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-6 text-center text-sm text-error">
          {catalogError}
        </p>
      ) : plans.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-dark/10 px-4 py-6 text-center text-sm text-foreground/50">
          No data plans are currently available for this network.
        </p>
      ) : (
        <div className="flex flex-col gap-3">
          {plans.map((plan) => {
            const isSelected = selectedPlanId === plan.variationCode;
            const showProviderName =
              plan.providerName.trim() !== "" &&
              plan.providerName.trim().toLowerCase() !==
                plan.displayName.trim().toLowerCase();

            return (
              <button
                key={plan.variationCode}
                type="button"
                onClick={() => onChange(plan.variationCode)}
                className={`rounded-2xl border p-4 text-left transition-colors ${
                  isSelected
                    ? "border-primary bg-primary/10"
                    : "border-dark/10 bg-white hover:border-primary/40"
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <p className="font-bold text-foreground">{plan.displayName}</p>
                    {showProviderName ? (
                      <p className="mt-1 truncate text-xs text-foreground/45">
                        {plan.providerName}
                      </p>
                    ) : null}
                    <div className="mt-2 flex flex-wrap gap-2">
                      {plan.isPopular ? <PlanBadge label="Popular" /> : null}
                      {plan.dataSizeLabel ? (
                        <PlanBadge label={plan.dataSizeLabel} />
                      ) : null}
                      {plan.validityLabel ? (
                        <PlanBadge label={plan.validityLabel} />
                      ) : null}
                    </div>
                  </div>
                  <p className="shrink-0 text-base font-bold text-dark">
                    {formatNaira(plan.price)}
                  </p>
                </div>
              </button>
            );
          })}
        </div>
      )}
    </FormField>
  );
}
