"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { validateOperatorAccess } from "@/lib/api/operatorAuth";
import { ApiError } from "@/lib/api/client";
import {
  isOperatorAuthError,
  isOperatorConnectivityError,
  OPERATOR_AUTH_EXPIRED_EVENT,
} from "@/lib/ops/operatorAuth";
import {
  clearOperatorKey,
  getOperatorKey,
  hasOperatorKey,
  setOperatorKey,
} from "@/lib/ops/operatorKey";
import {
  INVALID_OPERATOR_KEY_FORMAT_MESSAGE,
  INVALID_OPERATOR_KEY_MESSAGE,
  isOperatorKeyFormatValid,
  normalizeOperatorKeyInput,
  OPERATOR_API_UNAVAILABLE_MESSAGE,
  OPERATOR_SESSION_EXPIRED_MESSAGE,
} from "@/lib/ops/operatorSession";

export type OperatorAuthStatus =
  | "locked"
  | "validating"
  | "authenticated"
  | "invalid_key"
  | "api_unavailable"
  | "session_expired";

type OperatorAuthContextValue = {
  status: OperatorAuthStatus;
  error: string | null;
  unlock: (key: string) => Promise<void>;
  lock: () => void;
  isAuthenticated: boolean;
};

const OperatorAuthContext = createContext<OperatorAuthContextValue | null>(null);

async function verifyStoredKey(key: string): Promise<OperatorAuthStatus> {
  try {
    const result = await validateOperatorAccess(key);

    if (result.authenticated) {
      return "authenticated";
    }

    return "invalid_key";
  } catch (error) {
    if (isOperatorAuthError(error)) {
      return "invalid_key";
    }

    if (isOperatorConnectivityError(error)) {
      return "api_unavailable";
    }

    if (error instanceof ApiError) {
      return "invalid_key";
    }

    return "api_unavailable";
  }
}

export function OperatorAuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<OperatorAuthStatus>("locked");
  const [error, setError] = useState<string | null>(null);
  const [initialized, setInitialized] = useState(false);

  const lock = useCallback(() => {
    clearOperatorKey();
    setStatus("locked");
    setError(null);
  }, []);

  const unlock = useCallback(async (rawKey: string) => {
    const key = normalizeOperatorKeyInput(rawKey);

    if (!isOperatorKeyFormatValid(key)) {
      setStatus("invalid_key");
      setError(INVALID_OPERATOR_KEY_FORMAT_MESSAGE);
      return;
    }

    setStatus("validating");
    setError(null);

    const nextStatus = await verifyStoredKey(key);

    if (nextStatus === "authenticated") {
      setOperatorKey(key);
      setStatus("authenticated");
      setError(null);
      return;
    }

    clearOperatorKey();

    if (nextStatus === "api_unavailable") {
      setStatus("api_unavailable");
      setError(OPERATOR_API_UNAVAILABLE_MESSAGE);
      return;
    }

    setStatus("invalid_key");
    setError(INVALID_OPERATOR_KEY_MESSAGE);
  }, []);

  useEffect(() => {
    let cancelled = false;

    const bootstrap = async () => {
      if (!hasOperatorKey()) {
        if (!cancelled) {
          setStatus("locked");
          setInitialized(true);
        }

        return;
      }

      const storedKey = getOperatorKey();

      if (!storedKey || !isOperatorKeyFormatValid(storedKey)) {
        clearOperatorKey();

        if (!cancelled) {
          setStatus("locked");
          setInitialized(true);
        }

        return;
      }

      setStatus("validating");
      setError(null);

      const nextStatus = await verifyStoredKey(storedKey);

      if (cancelled) {
        return;
      }

      if (nextStatus === "authenticated") {
        setStatus("authenticated");
        setError(null);
        setInitialized(true);
        return;
      }

      clearOperatorKey();

      if (nextStatus === "api_unavailable") {
        setStatus("api_unavailable");
        setError(OPERATOR_API_UNAVAILABLE_MESSAGE);
      } else {
        setStatus("invalid_key");
        setError(INVALID_OPERATOR_KEY_MESSAGE);
      }

      setInitialized(true);
    };

    void bootstrap();

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    const handleAuthExpired = () => {
      clearOperatorKey();
      setStatus("session_expired");
      setError(OPERATOR_SESSION_EXPIRED_MESSAGE);
    };

    window.addEventListener(OPERATOR_AUTH_EXPIRED_EVENT, handleAuthExpired);

    return () => {
      window.removeEventListener(OPERATOR_AUTH_EXPIRED_EVENT, handleAuthExpired);
    };
  }, []);

  const value = useMemo<OperatorAuthContextValue>(
    () => ({
      status,
      error,
      unlock,
      lock,
      isAuthenticated: status === "authenticated",
    }),
    [status, error, unlock, lock],
  );

  if (!initialized) {
    return (
      <div className="flex min-h-full flex-1 items-center justify-center px-4 py-16">
        <div className="h-10 w-10 animate-spin rounded-full border-4 border-success/20 border-t-success" />
      </div>
    );
  }

  return (
    <OperatorAuthContext.Provider value={value}>
      {children}
    </OperatorAuthContext.Provider>
  );
}

export function useOperatorAuth(): OperatorAuthContextValue {
  const context = useContext(OperatorAuthContext);

  if (!context) {
    throw new Error("useOperatorAuth must be used within OperatorAuthProvider.");
  }

  return context;
}
