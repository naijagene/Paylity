import { getOpsApiBaseUrl } from "@/lib/api/ops";

export type HealthResponse = {
  status: string;
  environment?: string;
  checks?: {
    database?: string;
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
