import type { MeterType } from "@/lib/checkout/types";
import { FormField } from "./ValidationMessage";

type MeterTypeSelectorProps = {
  value: MeterType;
  onChange: (value: MeterType) => void;
};

export function MeterTypeSelector({ value, onChange }: MeterTypeSelectorProps) {
  return (
    <FormField label="Meter type" htmlFor="meter-prepaid">
      <div className="grid grid-cols-2 gap-2 rounded-2xl bg-dark/[0.03] p-1.5">
        {(["prepaid", "postpaid"] as const).map((type) => {
          const isActive = value === type;
          return (
            <button
              key={type}
              id={type === "prepaid" ? "meter-prepaid" : undefined}
              type="button"
              onClick={() => onChange(type)}
              className={`min-h-11 rounded-xl px-4 py-3 text-sm font-semibold capitalize transition-colors ${
                isActive
                  ? "bg-white text-foreground shadow-sm"
                  : "text-foreground/60 hover:text-foreground"
              }`}
            >
              {type}
            </button>
          );
        })}
      </div>
    </FormField>
  );
}
