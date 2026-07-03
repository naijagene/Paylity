"use client";

import { useEffect } from "react";
import { pruneTransactionSession } from "@/lib/transaction/session";

type TransactionSessionCleanupProps = {
  children?: React.ReactNode;
};

export function TransactionSessionCleanup({
  children,
}: TransactionSessionCleanupProps) {
  useEffect(() => {
    pruneTransactionSession();
  }, []);

  return children ?? null;
}
