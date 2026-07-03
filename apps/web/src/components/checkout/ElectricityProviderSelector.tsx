import { DISCOS } from "@/lib/checkout/constants";
import { FormField } from "./ValidationMessage";

type ElectricityProviderSelectorProps = {
  value: string;
  discos?: Array<{ value: string; label: string }>;
  onChange: (value: string) => void;
  error?: string;
};

export function ElectricityProviderSelector({
  value,
  discos = DISCOS.map((disco) => ({ value: disco, label: disco })),
  onChange,
  error,
}: ElectricityProviderSelectorProps) {
  return (
    <FormField label="Electricity provider" htmlFor="disco" error={error}>
      <select
        id="disco"
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className="w-full rounded-2xl border border-dark/10 bg-white px-4 py-3.5 text-base text-foreground outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/20"
      >
        <option value="">Select provider</option>
        {discos.map((disco) => (
          <option key={disco.value} value={disco.value}>
            {disco.label}
          </option>
        ))}
      </select>
    </FormField>
  );
}
