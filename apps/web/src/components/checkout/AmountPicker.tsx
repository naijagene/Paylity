import { formatNaira } from "@/lib/checkout/formatNaira";
import { FormField, TextInput } from "./ValidationMessage";
import { GuestLimitBanner } from "./GuestLimitBanner";

type AmountPickerProps = {
  amounts: readonly number[];
  selectedAmount: number;
  customAmount: string;
  onSelectAmount: (amount: number) => void;
  onCustomAmountChange: (value: string) => void;
  error?: string;
  isOverGuestLimit: boolean;
  onReduceAmount?: () => void;
};

export function AmountPicker({
  amounts,
  selectedAmount,
  customAmount,
  onSelectAmount,
  onCustomAmountChange,
  error,
  isOverGuestLimit,
  onReduceAmount,
}: AmountPickerProps) {
  return (
    <FormField
      label="Amount"
      htmlFor="custom-amount"
      error={error}
      hint="Guest payments up to ₦10,000"
    >
      <div className="grid grid-cols-3 gap-2">
        {amounts.map((amount) => {
          const isSelected = selectedAmount === amount && !customAmount;
          return (
            <button
              key={amount}
              type="button"
              onClick={() => onSelectAmount(amount)}
              className={`min-h-11 rounded-2xl border px-3 py-3 text-sm font-semibold transition-colors ${
                isSelected
                  ? "border-primary bg-primary/10 text-dark"
                  : "border-dark/10 bg-white text-foreground hover:border-primary/40"
              }`}
            >
              {formatNaira(amount)}
            </button>
          );
        })}
      </div>

      <div className="mt-3">
        <TextInput
          id="custom-amount"
          value={customAmount}
          onChange={onCustomAmountChange}
          inputMode="numeric"
          placeholder="Enter custom amount"
        />
      </div>

      {isOverGuestLimit ? (
        <div className="mt-3">
          <GuestLimitBanner onReduceAmount={onReduceAmount} />
        </div>
      ) : null}
    </FormField>
  );
}

type ReadOnlyAmountProps = {
  amount: number;
  label?: string;
};

export function ReadOnlyAmount({ amount, label = "Amount" }: ReadOnlyAmountProps) {
  return (
    <div className="mb-4 rounded-2xl border border-dark/10 bg-dark/[0.02] px-4 py-3.5">
      <p className="text-sm font-semibold text-foreground/60">{label}</p>
      <p className="mt-1 text-lg font-bold text-foreground">{formatNaira(amount)}</p>
    </div>
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
