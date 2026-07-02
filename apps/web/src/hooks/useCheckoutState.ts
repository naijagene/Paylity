"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import {
  CHECKOUT_STORAGE_KEY,
  CONVENIENCE_FEE,
  DATA_PLANS,
  GATEWAY_FEE,
} from "@/lib/checkout/constants";
import {
  calculatePayableAmount,
  isOverGuestProductLimit,
} from "@/lib/checkout/pricing";
import type {
  CheckoutFields,
  CheckoutState,
  CheckoutStep,
  FieldErrors,
  ProductType,
} from "@/lib/checkout/types";
import type { InitializeCheckoutResponse } from "@/lib/api/checkout";

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
    productAmount: 0,
    convenienceFee: CONVENIENCE_FEE,
    gatewayFee: GATEWAY_FEE,
    payableAmount: 0,
    customProductAmount: "",
    transactionRef: null,
    transactionInitialized: false,
  };
}

type LegacyCheckoutState = CheckoutState & {
  amount?: number;
  fee?: number;
  total?: number;
  customAmount?: string;
};

function normalizeStoredState(
  parsed: LegacyCheckoutState,
  product: ProductType,
): CheckoutState {
  if ("productAmount" in parsed && parsed.productAmount !== undefined) {
    return {
      ...parsed,
      product,
      convenienceFee: parsed.convenienceFee ?? CONVENIENCE_FEE,
      gatewayFee: parsed.gatewayFee ?? GATEWAY_FEE,
      transactionInitialized: parsed.transactionInitialized ?? false,
    };
  }

  const productAmount = parsed.amount ?? 0;

  return {
    product,
    step: parsed.step ?? "form",
    fields: parsed.fields ?? defaultFields(),
    productAmount,
    convenienceFee: parsed.fee ?? CONVENIENCE_FEE,
    gatewayFee: GATEWAY_FEE,
    payableAmount: parsed.total ?? calculatePayableAmount(productAmount),
    customProductAmount: parsed.customAmount ?? "",
    transactionRef: parsed.transactionRef ?? null,
    transactionInitialized: false,
  };
}

function loadStoredState(product: ProductType): CheckoutState | null {
  if (typeof window === "undefined") return null;

  try {
    const raw = sessionStorage.getItem(CHECKOUT_STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as LegacyCheckoutState;
    if (parsed.product !== product) return null;
    return normalizeStoredState(parsed, product);
  } catch {
    return null;
  }
}

function deriveSelectedProductAmount(stored: CheckoutState): number {
  if (stored.product === "data" || stored.productAmount <= 0) return 0;

  const quickPickAmounts = [100, 200, 500, 1000, 2000, 5000, 10000];
  return quickPickAmounts.includes(stored.productAmount)
    ? stored.productAmount
    : 0;
}

function computeProductAmount(
  product: ProductType,
  fields: CheckoutFields,
  customProductAmount: string,
  selectedProductAmount: number,
): number {
  if (product === "data") {
    const plan = DATA_PLANS.find((item) => item.id === fields.dataPlan);
    return plan?.price ?? 0;
  }

  if (selectedProductAmount > 0) return selectedProductAmount;

  const parsed = parseInt(customProductAmount.replace(/\D/g, ""), 10);
  return Number.isNaN(parsed) ? 0 : parsed;
}

export function useCheckoutState(product: ProductType) {
  const router = useRouter();

  const [state, setState] = useState<CheckoutState>(() => {
    return loadStoredState(product) ?? createInitialState(product);
  });
  const [selectedProductAmount, setSelectedProductAmount] = useState(() => {
    const stored = loadStoredState(product);
    return stored ? deriveSelectedProductAmount(stored) : 0;
  });
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});

  const productAmount = useMemo(
    () =>
      computeProductAmount(
        state.product,
        state.fields,
        state.customProductAmount,
        selectedProductAmount,
      ),
    [state.product, state.fields, state.customProductAmount, selectedProductAmount],
  );

  const convenienceFee = CONVENIENCE_FEE;
  const gatewayFee = GATEWAY_FEE;
  const payableAmount = useMemo(
    () => calculatePayableAmount(productAmount, gatewayFee),
    [productAmount, gatewayFee],
  );

  useEffect(() => {
    const nextState: CheckoutState = {
      ...state,
      product,
      productAmount,
      convenienceFee,
      gatewayFee,
      payableAmount,
    };

    sessionStorage.setItem(CHECKOUT_STORAGE_KEY, JSON.stringify(nextState));
  }, [state, product, productAmount, convenienceFee, gatewayFee, payableAmount]);

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
    setState((prev) => ({
      ...prev,
      step,
      ...(step === "form"
        ? {
            transactionInitialized: false,
            transactionRef: null,
          }
        : {}),
    }));
  }, []);

  const setTransactionInitialized = useCallback(
    (transaction: InitializeCheckoutResponse) => {
      setState((prev) => ({
        ...prev,
        transactionRef: transaction.reference,
        transactionInitialized: true,
        productAmount: transaction.product_amount,
        convenienceFee: transaction.convenience_fee,
        gatewayFee: transaction.gateway_fee,
        payableAmount: transaction.payable_amount,
      }));
    },
    [],
  );

  const setCustomProductAmount = useCallback((value: string) => {
    setSelectedProductAmount(0);
    setState((prev) => ({ ...prev, customProductAmount: value }));
  }, []);

  const selectProductAmount = useCallback((value: number) => {
    setSelectedProductAmount(value);
    setState((prev) => ({ ...prev, customProductAmount: "" }));
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

  const isOverGuestLimit = isOverGuestProductLimit(productAmount);

  return {
    product,
    state,
    productAmount,
    convenienceFee,
    gatewayFee,
    payableAmount,
    selectedProductAmount,
    fieldErrors,
    setFieldErrors,
    isOverGuestLimit,
    setProduct,
    updateField,
    setStep,
    setTransactionInitialized,
    setCustomProductAmount,
    selectProductAmount,
    resetMeterVerification,
    markMeterVerified,
  };
}
