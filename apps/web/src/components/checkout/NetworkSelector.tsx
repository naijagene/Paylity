import { FormField } from "./ValidationMessage";

type NetworkSelectorProps = {
  value: string;
  networks: readonly string[];
  loading?: boolean;
  catalogError?: string | null;
  onChange: (value: string) => void;
  error?: string;
};

export function NetworkSelector({
  value,
  networks,
  loading = false,
  catalogError = null,
  onChange,
  error,
}: NetworkSelectorProps) {
  return (
    <FormField label="Network" htmlFor="network" error={error}>
      {loading ? (
        <p className="rounded-2xl border border-dashed border-dark/10 px-4 py-6 text-center text-sm text-foreground/50">
          Loading available networks…
        </p>
      ) : catalogError ? (
        <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-6 text-center text-sm text-error">
          {catalogError}
        </p>
      ) : networks.length === 0 ? (
        <p className="rounded-2xl border border-dashed border-dark/10 px-4 py-6 text-center text-sm text-foreground/50">
          No networks are currently available.
        </p>
      ) : (
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
      )}
    </FormField>
  );
}
