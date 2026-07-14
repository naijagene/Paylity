"use client";

import { useState } from "react";
import { Button } from "@/components/Button";

type VoucherCodeInputProps = {
  value: string;
  applied: boolean;
  discountAmount: number;
  error?: string | null;
  loading?: boolean;
  disabled?: boolean;
  onChange: (value: string) => void;
  onApply: () => void;
  onClear: () => void;
};

export function VoucherCodeInput({
  value,
  applied,
  discountAmount,
  error,
  loading = false,
  disabled = false,
  onChange,
  onApply,
  onClear,
}: VoucherCodeInputProps) {
  const [touched, setTouched] = useState(false);

  return (
    <div className="rounded-2xl border border-dark/5 bg-slate-50 p-4">
      <label htmlFor="voucher-code" className="text-sm font-semibold text-foreground">
        Voucher Code
      </label>
      <p className="mt-1 text-xs text-muted">Launch vouchers apply to airtime product amount only.</p>
      <div className="mt-3 flex flex-col gap-3 sm:flex-row">
        <input
          id="voucher-code"
          type="text"
          value={value}
          disabled={disabled || applied}
          onChange={(event) => onChange(event.target.value.toUpperCase())}
          onBlur={() => setTouched(true)}
          placeholder="Enter voucher code"
          className="w-full rounded-xl border border-border px-4 py-3 text-sm uppercase"
        />
        {applied ? (
          <Button type="button" variant="outline" onClick={onClear}>
            Remove
          </Button>
        ) : (
          <Button type="button" variant="secondary" disabled={disabled || loading || !value.trim()} onClick={onApply}>
            {loading ? "Applying…" : "Apply"}
          </Button>
        )}
      </div>
      {applied ? (
        <p className="mt-2 text-sm font-semibold text-success">
          Voucher applied. You save ₦{discountAmount.toLocaleString("en-NG")} on product amount.
        </p>
      ) : null}
      {error && touched ? <p className="mt-2 text-sm text-danger">{error}</p> : null}
    </div>
  );
}
