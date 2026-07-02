import { DISCOS } from "@/lib/checkout/constants";
import { FormField } from "./ValidationMessage";

type ElectricityProviderSelectorProps = {
  value: string;
  onChange: (value: string) => void;
  error?: string;
};

export function ElectricityProviderSelector({
  value,
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
        {DISCOS.map((disco) => (
          <option key={disco} value={disco}>
            {disco}
          </option>
        ))}
      </select>
    </FormField>
  );
}
