import { FormField } from "./ValidationMessage";

type ElectricityProviderSelectorProps = {
  value: string;
  discos: Array<{ value: string; label: string }>;
  loading?: boolean;
  catalogError?: string | null;
  onChange: (value: string) => void;
  error?: string;
};

export function ElectricityProviderSelector({
  value,
  discos,
  loading = false,
  catalogError = null,
  onChange,
  error,
}: ElectricityProviderSelectorProps) {
  return (
    <FormField label="Electricity provider" htmlFor="disco" error={error}>
      {loading ? (
        <p className="rounded-2xl border border-dashed border-dark/10 px-4 py-6 text-center text-sm text-foreground/50">
          Loading electricity providers…
        </p>
      ) : catalogError ? (
        <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-6 text-center text-sm text-error">
          {catalogError}
        </p>
      ) : (
        <select
          id="disco"
          value={value}
          onChange={(event) => onChange(event.target.value)}
          disabled={discos.length === 0}
          className="w-full rounded-2xl border border-dark/10 bg-white px-4 py-3.5 text-base text-foreground outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:bg-dark/[0.03]"
        >
          <option value="">
            {discos.length === 0
              ? "No electricity providers available"
              : "Select provider"}
          </option>
          {discos.map((disco) => (
            <option key={disco.value} value={disco.value}>
              {disco.label}
            </option>
          ))}
        </select>
      )}
    </FormField>
  );
}
