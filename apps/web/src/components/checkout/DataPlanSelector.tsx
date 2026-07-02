import { DATA_PLANS } from "@/lib/checkout/constants";
import { formatNaira } from "@/lib/checkout/formatNaira";
import { FormField } from "./ValidationMessage";

type DataPlanSelectorProps = {
  network: string;
  selectedPlanId: string;
  onChange: (planId: string) => void;
  error?: string;
};

export function DataPlanSelector({
  network,
  selectedPlanId,
  onChange,
  error,
}: DataPlanSelectorProps) {
  const plans = DATA_PLANS.filter((plan) => !network || plan.network === network);

  return (
    <FormField label="Data plan" htmlFor="data-plan" error={error}>
      {!network ? (
        <p className="rounded-2xl border border-dashed border-dark/10 px-4 py-6 text-center text-sm text-foreground/50">
          Select a network to see available plans
        </p>
      ) : (
        <div className="flex flex-col gap-3">
          {plans.map((plan) => {
            const isSelected = selectedPlanId === plan.id;
            return (
              <button
                key={plan.id}
                type="button"
                onClick={() => onChange(plan.id)}
                className={`rounded-2xl border p-4 text-left transition-colors ${
                  isSelected
                    ? "border-primary bg-primary/10"
                    : "border-dark/10 bg-white hover:border-primary/40"
                }`}
              >
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="font-bold text-foreground">{plan.name}</p>
                    <p className="mt-1 text-sm text-foreground/60">
                      {plan.size} · {plan.validity} · {plan.network}
                    </p>
                  </div>
                  <p className="text-base font-bold text-dark">
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
