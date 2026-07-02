import { PRODUCT_SCHEMAS } from "@/lib/checkout/checkoutSchemas";
import type { ProductType } from "@/lib/checkout/types";

type ProductTabsProps = {
  activeProduct: ProductType;
  onChange: (product: ProductType) => void;
};

export function ProductTabs({ activeProduct, onChange }: ProductTabsProps) {
  return (
    <div className="mb-6 grid grid-cols-3 gap-2 rounded-2xl bg-dark/[0.03] p-1.5">
      {PRODUCT_SCHEMAS.map((schema) => {
        const isActive = schema.id === activeProduct;
        return (
          <button
            key={schema.id}
            type="button"
            onClick={() => onChange(schema.id)}
            className={`min-h-11 rounded-xl px-2 py-2.5 text-xs font-semibold transition-colors sm:text-sm ${
              isActive
                ? "bg-white text-foreground shadow-sm"
                : "text-foreground/60 hover:text-foreground"
            }`}
          >
            {schema.id === "airtime" && "Airtime"}
            {schema.id === "data" && "Data"}
            {schema.id === "electricity" && "Electricity"}
          </button>
        );
      })}
    </div>
  );
}
