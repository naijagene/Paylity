"use client";

import { CheckoutProcessingScreen } from "@/components/checkout/CheckoutProcessingScreen";
import type { ProductType } from "@/lib/checkout/types";

type PaymentPendingOverlayProps = {
  product: ProductType;
  transactionRef?: string | null;
};

export function PaymentPendingOverlay({
  product,
  transactionRef,
}: PaymentPendingOverlayProps) {
  return (
    <CheckoutProcessingScreen product={product} transactionRef={transactionRef} />
  );
}
