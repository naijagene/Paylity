import { DATA_PLANS, GUEST_MAX_AMOUNT, MIN_AMOUNT } from "./constants";
import { isValidNigerianPhone } from "./normalizePhone";
import type { CheckoutFields, FieldErrors, ProductType } from "./types";

export const ERROR_MESSAGES = {
  PHONE_INVALID: "Enter a valid Nigerian phone number",
  EMAIL_INVALID: "Enter a valid email address",
  AMOUNT_MIN: (min: number) => `Minimum amount is ₦${min.toLocaleString("en-NG")}`,
  AMOUNT_MAX_GUEST: "Guest payments are limited to ₦10,000",
  METER_INVALID: "Enter a valid meter number",
  PLAN_REQUIRED: "Select a data plan to continue",
  NETWORK_REQUIRED: "Select a network",
  DISCO_REQUIRED: "Select an electricity provider",
  NAME_REQUIRED: "Enter the customer name",
  REQUIRED: "This field is required",
  METER_NOT_VERIFIED: "Verify your meter before continuing",
} as const;

function validateEmail(value: string): string | undefined {
  if (!value.trim()) return undefined;
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailPattern.test(value.trim())) {
    return ERROR_MESSAGES.EMAIL_INVALID;
  }
  return undefined;
}

function validatePhone(value: string): string | undefined {
  if (!value.trim()) return ERROR_MESSAGES.REQUIRED;
  if (!isValidNigerianPhone(value)) return ERROR_MESSAGES.PHONE_INVALID;
  return undefined;
}

function validateAmount(amount: number, min = MIN_AMOUNT): string | undefined {
  if (!amount || amount < min) {
    return ERROR_MESSAGES.AMOUNT_MIN(min);
  }
  if (amount > GUEST_MAX_AMOUNT) {
    return ERROR_MESSAGES.AMOUNT_MAX_GUEST;
  }
  return undefined;
}

function validateMeterNumber(value: string): string | undefined {
  if (!value.trim()) return ERROR_MESSAGES.REQUIRED;
  const digits = value.replace(/\D/g, "");
  if (digits.length < 10 || digits.length > 13) {
    return ERROR_MESSAGES.METER_INVALID;
  }
  return undefined;
}

export function validateCheckoutForm(
  product: ProductType,
  fields: CheckoutFields,
  amount: number,
): FieldErrors {
  const errors: FieldErrors = {};

  const customerPhoneError = validatePhone(fields.customerPhone);
  if (customerPhoneError) errors.customerPhone = customerPhoneError;

  const emailError = validateEmail(fields.customerEmail);
  if (emailError) errors.customerEmail = emailError;

  if (product === "airtime" || product === "data") {
    if (!fields.network) {
      errors.network = ERROR_MESSAGES.NETWORK_REQUIRED;
    }

    const recipientPhone = fields.useMyNumber
      ? fields.customerPhone
      : fields.recipientPhone;
    const recipientError = validatePhone(recipientPhone);
    if (recipientError) {
      errors.recipientPhone = recipientError;
    }
  }

  if (product === "airtime" || product === "electricity") {
    const amountError = validateAmount(amount);
    if (amountError) errors.amount = amountError;
  }

  if (product === "data") {
    if (!fields.dataPlan) {
      errors.dataPlan = ERROR_MESSAGES.PLAN_REQUIRED;
    } else {
      const plan = DATA_PLANS.find((item) => item.id === fields.dataPlan);
      if (plan && plan.price > GUEST_MAX_AMOUNT) {
        errors.amount = ERROR_MESSAGES.AMOUNT_MAX_GUEST;
      }
    }
  }

  if (product === "electricity") {
    if (!fields.disco) {
      errors.disco = ERROR_MESSAGES.DISCO_REQUIRED;
    }

    const meterError = validateMeterNumber(fields.meterNumber);
    if (meterError) errors.meterNumber = meterError;

    if (!fields.customerName.trim()) {
      errors.customerName = ERROR_MESSAGES.NAME_REQUIRED;
    }

    if (!fields.meterVerified) {
      errors.meterNumber =
        errors.meterNumber ?? ERROR_MESSAGES.METER_NOT_VERIFIED;
    }
  }

  return errors;
}

export function isOverGuestLimit(total: number): boolean {
  return total > GUEST_MAX_AMOUNT;
}
