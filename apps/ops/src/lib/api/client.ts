export type ApiSuccessResponse<T> = {
  success: true;
  message: string;
  data: T;
  meta?: Record<string, unknown>;
};

export type ApiErrorResponse = {
  success: false;
  message: string;
  errors?: Record<string, unknown>;
};

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly errors: Record<string, unknown> = {},
    public readonly status = 400,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export class ApiOfflineError extends Error {
  constructor() {
    super(
      "PAYLITY API is currently unavailable. Please start the backend server and try again.",
    );
    this.name = "ApiOfflineError";
  }
}

const DEFAULT_API_BASE_URL = "http://127.0.0.1:8000/api/v1";

export function getApiBaseUrl(): string {
  return process.env.NEXT_PUBLIC_API_BASE_URL ?? DEFAULT_API_BASE_URL;
}

function isNetworkFailure(error: unknown): boolean {
  return (
    error instanceof TypeError ||
    (error instanceof Error &&
      (error.message.includes("Failed to fetch") ||
        error.message.includes("NetworkError") ||
        error.message.includes("fetch")))
  );
}

export async function apiRequest<T>(
  path: string,
  options: RequestInit = {},
): Promise<{ data: T; message: string }> {
  const url = `${getApiBaseUrl()}${path}`;

  try {
    const response = await fetch(url, {
      ...options,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        ...options.headers,
      },
    });

    let body: ApiSuccessResponse<T> | ApiErrorResponse;

    try {
      body = (await response.json()) as ApiSuccessResponse<T> | ApiErrorResponse;
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

    return {
      data: body.data,
      message: body.message,
    };
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
