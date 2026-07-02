"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import {
  CHECKOUT_STORAGE_KEY,
  DATA_PLANS,
  GUEST_MAX_AMOUNT,
} from "@/lib/checkout/constants";
import type {
  CheckoutFields,
  CheckoutState,
  CheckoutStep,
  FieldErrors,
  ProductType,
} from "@/lib/checkout/types";

const defaultFields = (): CheckoutFields => ({
  customerPhone: "",
  customerEmail: "",
  network: "",
  recipientPhone: "",
  dataPlan: "",
  disco: "",
  meterType: "prepaid",
  meterNumber: "",
  customerName: "",
  useMyNumber: true,
  meterVerified: false,
});

function createInitialState(product: ProductType): CheckoutState {
  return {
    product,
    step: "form",
    fields: defaultFields(),
    amount: 0,
    fee: 0,
    total: 0,
    customAmount: "",
    transactionRef: null,
  };
}

function loadStoredState(product: ProductType): CheckoutState | null {
  if (typeof window === "undefined") return null;

  try {
    const raw = sessionStorage.getItem(CHECKOUT_STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as CheckoutState;
    if (parsed.product !== product) return null;
    return parsed;
  } catch {
    return null;
  }
}

function deriveSelectedAmount(stored: CheckoutState): number {
  if (stored.product === "data" || stored.amount <= 0) return 0;

  const quickPickAmounts = [100, 200, 500, 1000, 2000, 5000, 10000];
  return quickPickAmounts.includes(stored.amount) ? stored.amount : 0;
}

function computeAmount(
  product: ProductType,
  fields: CheckoutFields,
  customAmount: string,
  selectedAmount: number,
): number {
  if (product === "data") {
    const plan = DATA_PLANS.find((item) => item.id === fields.dataPlan);
    return plan?.price ?? 0;
  }

  if (selectedAmount > 0) return selectedAmount;

  const parsed = parseInt(customAmount.replace(/\D/g, ""), 10);
  return Number.isNaN(parsed) ? 0 : parsed;
}

export function useCheckoutState(product: ProductType) {
  const router = useRouter();

  const [state, setState] = useState<CheckoutState>(() => {
    return loadStoredState(product) ?? createInitialState(product);
  });
  const [selectedAmount, setSelectedAmount] = useState(() => {
    const stored = loadStoredState(product);
    return stored ? deriveSelectedAmount(stored) : 0;
  });
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  const amount = useMemo(
    () =>
      computeAmount(state.product, state.fields, state.customAmount, selectedAmount),
    [state.product, state.fields, state.customAmount, selectedAmount],
  );

  const total = useMemo(() => amount + state.fee, [amount, state.fee]);

  useEffect(() => {
    const nextState: CheckoutState = {
      ...state,
      product,
      amount,
      total,
    };

    sessionStorage.setItem(CHECKOUT_STORAGE_KEY, JSON.stringify(nextState));
  }, [state, product, amount, total]);

  const setProduct = useCallback(
    (nextProduct: ProductType) => {
      router.replace(`/checkout?product=${nextProduct}`);
    },
    [router],
  );

  const updateField = useCallback(
    <K extends keyof CheckoutFields>(key: K, value: CheckoutFields[K]) => {
      setState((prev) => {
        const nextFields = { ...prev.fields, [key]: value };

        if (key === "network" && prev.product === "data") {
          nextFields.dataPlan = "";
        }

        if (key === "meterNumber") {
          nextFields.meterVerified = false;
          nextFields.customerName = "";
        }

        return { ...prev, fields: nextFields };
      });
    },
    [],
  );

  const setStep = useCallback((step: CheckoutStep) => {
    setState((prev) => ({ ...prev, step }));
  }, []);

  const setCustomAmount = useCallback((value: string) => {
    setSelectedAmount(0);
    setState((prev) => ({ ...prev, customAmount: value }));
  }, []);

  const selectAmount = useCallback((value: number) => {
    setSelectedAmount(value);
    setState((prev) => ({ ...prev, customAmount: "" }));
  }, []);

  const resetMeterVerification = useCallback(() => {
    setState((prev) => ({
      ...prev,
      fields: {
        ...prev.fields,
        meterVerified: false,
        customerName: "",
      },
    }));
  }, []);

  const markMeterVerified = useCallback((customerName: string) => {
    setState((prev) => ({
      ...prev,
      fields: {
        ...prev.fields,
        meterVerified: true,
        customerName,
      },
    }));
  }, []);

  const isOverGuestLimit = total > GUEST_MAX_AMOUNT;

  return {
    product,
    state,
    amount,
    total,
    selectedAmount,
    fieldErrors,
    setFieldErrors,
    isOverGuestLimit,
    setProduct,
    updateField,
    setStep,
    setCustomAmount,
    selectAmount,
    resetMeterVerification,
    markMeterVerified,
  };
}
