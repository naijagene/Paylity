import { ApiError, ApiOfflineError } from "@/lib/api/client";

const DEFAULT_OPS_API_BASE_URL = "http://127.0.0.1:8000/api/v1";

function getOpsApiBaseUrl(): string {
  return (
    process.env.NEXT_PUBLIC_OPERATOR_API_BASE_URL ??
    process.env.NEXT_PUBLIC_API_BASE_URL ??
    DEFAULT_OPS_API_BASE_URL
  );
}

export type OperatorAuthValidation = {
  authenticated: boolean;
  role: string;
};

type ApiSuccessResponse<T> = {
  success: true;
  message: string;
  data: T;
};

type ApiErrorResponse = {
  success: false;
  message: string;
  errors?: Record<string, unknown>;
};

function isNetworkFailure(error: unknown): boolean {
  return (
    error instanceof TypeError ||
    (error instanceof Error &&
      (error.message.includes("Failed to fetch") ||
        error.message.includes("NetworkError") ||
        error.message.includes("fetch")))
  );
}

export async function validateOperatorAccess(
  operatorKey: string,
): Promise<OperatorAuthValidation> {
  const url = `${getOpsApiBaseUrl()}/ops/auth/validate`;

  try {
    const response = await fetch(url, {
      method: "GET",
      headers: {
        Accept: "application/json",
        "X-Operator-Key": operatorKey,
      },
    });

    let body: ApiSuccessResponse<OperatorAuthValidation> | ApiErrorResponse;

    try {
      body = (await response.json()) as
        | ApiSuccessResponse<OperatorAuthValidation>
        | ApiErrorResponse;
    } catch {
      if (!response.ok) {
        throw new ApiError("Unexpected API response.", {}, response.status);
      }

      throw new ApiOfflineError();
    }

    if (!body.success) {
      throw new ApiError(
        body.message || "Request failed.",
        body.errors ?? {},
        response.status,
      );
    }

    if (!body.data.authenticated) {
      throw new ApiError(
        "Invalid operator access key.",
        { code: "OPERATOR_ACCESS_DENIED" },
        401,
      );
    }

    return body.data;
  } catch (error) {
    if (error instanceof ApiError || error instanceof ApiOfflineError) {
      throw error;
    }

    if (isNetworkFailure(error)) {
      throw new ApiOfflineError();
    }

    throw error;
  }
}
