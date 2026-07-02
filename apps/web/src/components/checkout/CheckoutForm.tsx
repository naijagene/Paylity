import { AIRTIME_AMOUNTS, ELECTRICITY_AMOUNTS } from "@/lib/checkout/constants";
import { maskPhone } from "@/lib/checkout/normalizePhone";
import type { CheckoutFields, FieldErrors, ProductType } from "@/lib/checkout/types";
import { AmountPicker, MeterVerifyField } from "./AmountPicker";
import { DataPlanSelector } from "./DataPlanSelector";
import { ElectricityProviderSelector } from "./ElectricityProviderSelector";
import { MeterTypeSelector } from "./MeterTypeSelector";
import { NetworkSelector } from "./NetworkSelector";
import { FormField, TextInput } from "./ValidationMessage";

type CheckoutFormProps = {
  product: ProductType;
  fields: CheckoutFields;
  selectedProductAmount: number;
  customProductAmount: string;
  productAmount: number;
  errors: FieldErrors;
  isOverGuestLimit: boolean;
  isVerifyingMeter: boolean;
  onFieldChange: <K extends keyof CheckoutFields>(
    key: K,
    value: CheckoutFields[K],
  ) => void;
  onSelectProductAmount: (productAmount: number) => void;
  onCustomProductAmountChange: (value: string) => void;
  onVerifyMeter: () => void;
  onReduceProductAmount: () => void;
};

export function CheckoutForm({
  product,
  fields,
  selectedProductAmount,
  customProductAmount,
  errors,
  isOverGuestLimit,
  isVerifyingMeter,
  onFieldChange,
  onSelectProductAmount,
  onCustomProductAmountChange,
  onVerifyMeter,
  onReduceProductAmount,
}: CheckoutFormProps) {
  return (
    <div className="rounded-3xl border border-dark/5 bg-white p-5 shadow-sm sm:p-6">
      <FormField
        label="Your phone number"
        htmlFor="customer-phone"
        error={errors.customerPhone}
        hint="For receipt and support"
      >
        <TextInput
          id="customer-phone"
          value={fields.customerPhone}
          onChange={(value) => onFieldChange("customerPhone", value)}
          inputMode="tel"
          placeholder="08012345678"
        />
      </FormField>

      <FormField
        label="Email (optional)"
        htmlFor="customer-email"
        error={errors.customerEmail}
      >
        <TextInput
          id="customer-email"
          value={fields.customerEmail}
          onChange={(value) => onFieldChange("customerEmail", value)}
          type="email"
          inputMode="email"
          placeholder="you@example.com"
        />
      </FormField>

      {(product === "airtime" || product === "data") && (
        <>
          <NetworkSelector
            value={fields.network}
            onChange={(value) => onFieldChange("network", value)}
            error={errors.network}
          />

          {product === "airtime" && (
            <>
              <div className="mb-4">
                <label className="flex min-h-11 cursor-pointer items-center gap-3 rounded-2xl border border-dark/10 px-4 py-3">
                  <input
                    type="checkbox"
                    checked={fields.useMyNumber}
                    onChange={(event) =>
                      onFieldChange("useMyNumber", event.target.checked)
                    }
                    className="h-4 w-4 rounded border-dark/20 text-primary focus:ring-primary"
                  />
                  <span className="text-sm font-medium text-foreground">
                    Use my number ({maskPhone(fields.customerPhone) || "same as above"})
                  </span>
                </label>
              </div>

              {!fields.useMyNumber && (
                <FormField
                  label="Phone to recharge"
                  htmlFor="recipient-phone"
                  error={errors.recipientPhone}
                >
                  <TextInput
                    id="recipient-phone"
                    value={fields.recipientPhone}
                    onChange={(value) => onFieldChange("recipientPhone", value)}
                    inputMode="tel"
                    placeholder="08012345678"
                  />
                </FormField>
              )}
            </>
          )}

          {product === "data" && (
            <FormField
              label="Phone number"
              htmlFor="recipient-phone"
              error={errors.recipientPhone}
            >
              <TextInput
                id="recipient-phone"
                value={fields.recipientPhone}
                onChange={(value) => onFieldChange("recipientPhone", value)}
                inputMode="tel"
                placeholder="08012345678"
              />
            </FormField>
          )}
        </>
      )}

      {product === "airtime" && (
        <AmountPicker
          amounts={AIRTIME_AMOUNTS}
          selectedProductAmount={selectedProductAmount}
          customProductAmount={customProductAmount}
          onSelectProductAmount={onSelectProductAmount}
          onCustomProductAmountChange={onCustomProductAmountChange}
          error={errors.productAmount}
          isOverGuestLimit={isOverGuestLimit}
          onReduceProductAmount={onReduceProductAmount}
        />
      )}

      {product === "data" && (
        <DataPlanSelector
          network={fields.network}
          selectedPlanId={fields.dataPlan}
          onChange={(value) => onFieldChange("dataPlan", value)}
          error={errors.dataPlan ?? errors.productAmount}
        />
      )}

      {product === "electricity" && (
        <>
          <ElectricityProviderSelector
            value={fields.disco}
            onChange={(value) => onFieldChange("disco", value)}
            error={errors.disco}
          />

          <MeterTypeSelector
            value={fields.meterType}
            onChange={(value) => onFieldChange("meterType", value)}
          />

          <MeterVerifyField
            meterNumber={fields.meterNumber}
            customerName={fields.customerName}
            meterVerified={fields.meterVerified}
            isVerifying={isVerifyingMeter}
            onMeterNumberChange={(value) => onFieldChange("meterNumber", value)}
            onVerify={onVerifyMeter}
            error={errors.meterNumber}
          />

          {fields.meterVerified && (
            <FormField
              label="Customer name"
              htmlFor="customer-name"
              error={errors.customerName}
            >
              <TextInput
                id="customer-name"
                value={fields.customerName}
                onChange={(value) => onFieldChange("customerName", value)}
                placeholder="Verified customer name"
              />
            </FormField>
          )}

          <AmountPicker
            amounts={ELECTRICITY_AMOUNTS}
            selectedProductAmount={selectedProductAmount}
            customProductAmount={customProductAmount}
            onSelectProductAmount={onSelectProductAmount}
            onCustomProductAmountChange={onCustomProductAmountChange}
            error={errors.productAmount}
            isOverGuestLimit={isOverGuestLimit}
            onReduceProductAmount={onReduceProductAmount}
          />
        </>
      )}
    </div>
  );
}
