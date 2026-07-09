import { apiRequest } from "./client";

export type PlatformStatus = {
  checkout_enabled: boolean;
  maintenance_mode: boolean;
  incident_mode: boolean;
  message: string | null;
};

export async function fetchPlatformStatus(): Promise<PlatformStatus> {
  const { data } = await apiRequest<PlatformStatus>("/platform/status");
  return data;
}
