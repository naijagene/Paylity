import { formatNaira } from "@/lib/checkout/formatNaira";
import { FormField, TextInput } from "./ValidationMessage";
import { GuestLimitBanner } from "./GuestLimitBanner";

type AmountPickerProps = {
  amounts: readonly number[];
  selectedProductAmount: number;
  customProductAmount: string;
  onSelectProductAmount: (productAmount: number) => void;
  onCustomProductAmountChange: (value: string) => void;
  error?: string;
  isOverGuestLimit: boolean;
  onReduceProductAmount?: () => void;
};

export function AmountPicker({
  amounts,
  selectedProductAmount,
  customProductAmount,
  onSelectProductAmount,
  onCustomProductAmountChange,
  error,
  isOverGuestLimit,
  onReduceProductAmount,
}: AmountPickerProps) {
  return (
    <FormField
      label="Product amount"
      htmlFor="custom-product-amount"
      error={error}
      hint="Guest product amount up to ₦10,000"
    >
      <div className="grid grid-cols-3 gap-2">
        {amounts.map((productAmount) => {
          const isSelected =
            selectedProductAmount === productAmount && !customProductAmount;
          return (
            <button
              key={productAmount}
              type="button"
              onClick={() => onSelectProductAmount(productAmount)}
              className={`min-h-11 rounded-2xl border px-3 py-3 text-sm font-semibold transition-colors ${
                isSelected
                  ? "border-primary bg-primary/10 text-dark"
                  : "border-dark/10 bg-white text-foreground hover:border-primary/40"
              }`}
            >
              {formatNaira(productAmount)}
            </button>
          );
        })}
      </div>

      <div className="mt-3">
        <TextInput
          id="custom-product-amount"
          value={customProductAmount}
          onChange={onCustomProductAmountChange}
          inputMode="numeric"
          placeholder="Enter custom product amount"
        />
      </div>

      {isOverGuestLimit ? (
        <div className="mt-3">
          <GuestLimitBanner onReduceProductAmount={onReduceProductAmount} />
        </div>
      ) : null}
    </FormField>
  );
}

export function MeterVerifyField({
  meterNumber,
  customerName,
  meterVerified,
  isVerifying,
  onMeterNumberChange,
  onVerify,
  error,
}: {
  meterNumber: string;
  customerName: string;
  meterVerified: boolean;
  isVerifying: boolean;
  onMeterNumberChange: (value: string) => void;
  onVerify: () => void;
  error?: string;
}) {
  return (
    <FormField label="Meter number" htmlFor="meter-number" error={error}>
      <div className="flex gap-2">
        <TextInput
          id="meter-number"
          value={meterNumber}
          onChange={onMeterNumberChange}
          inputMode="numeric"
          placeholder="Enter meter number"
        />
        <button
          type="button"
          onClick={onVerify}
          disabled={isVerifying || !meterNumber.trim()}
          className="shrink-0 rounded-2xl bg-dark px-4 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#1f1f1f] disabled:opacity-50"
        >
          {isVerifying ? "Verifying…" : "Verify meter"}
        </button>
      </div>

      {meterVerified ? (
        <p className="mt-2 text-sm font-medium text-success">
          Meter verified · {customerName}
        </p>
      ) : null}
    </FormField>
  );
}
