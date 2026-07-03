import { NETWORKS } from "@/lib/checkout/constants";
import { FormField } from "./ValidationMessage";

type NetworkSelectorProps = {
  value: string;
  networks?: readonly string[];
  onChange: (value: string) => void;
  error?: string;
};

export function NetworkSelector({
  value,
  networks = NETWORKS,
  onChange,
  error,
}: NetworkSelectorProps) {
  return (
    <FormField label="Network" htmlFor="network" error={error}>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {networks.map((network) => {
          const isSelected = value === network;
          return (
            <button
              key={network}
              type="button"
              onClick={() => onChange(network)}
              className={`min-h-11 rounded-2xl border px-3 py-3 text-sm font-semibold transition-colors ${
                isSelected
                  ? "border-primary bg-primary/10 text-dark"
                  : "border-dark/10 bg-white text-foreground hover:border-primary/40"
              }`}
            >
              {network}
            </button>
          );
        })}
      </div>
    </FormField>
  );
}
