import { getOpsApiBaseUrl } from "@/lib/api/ops";

export type HealthResponse = {
  status: string;
  environment?: string;
  version?: string;
  checks?: {
    api?: string;
    database?: string;
    cache?: string;
    queue?: {
      status?: string;
      connection?: string;
      pending_jobs?: number;
      failed_jobs?: number;
    };
    mail?: string;
    paystack?: string;
    vtpass?: string;
  };
};

export async function fetchPublicHealth(): Promise<HealthResponse> {
  const response = await fetch(`${getOpsApiBaseUrl()}/health`, {
    headers: { Accept: "application/json" },
  });

  const body = (await response.json()) as {
    success: boolean;
    data: HealthResponse;
  };

  return body.data;
}
