import { getApiBaseUrl } from "@/lib/api/client";

export type PlatformStatus = {
  checkout_enabled: boolean;
  maintenance_mode: boolean;
  incident_mode: boolean;
  message: string | null;
};

export async function fetchPlatformStatus(): Promise<PlatformStatus> {
  const response = await fetch(`${getApiBaseUrl()}/platform/status`, {
    headers: { Accept: "application/json" },
    next: { revalidate: 30 },
  });

  const body = (await response.json()) as {
    success: boolean;
    data: PlatformStatus;
  };

  return body.data;
}
