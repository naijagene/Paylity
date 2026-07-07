import { opsRequest } from "@/lib/api/ops";

export type SystemSettingRecord = {
  key: string;
  value: unknown;
  type: string;
  description?: string | null;
  updated_at?: string | null;
};

export type FeatureFlagRecord = {
  key: string;
  enabled: boolean;
  stored_enabled: boolean;
  description?: string | null;
  updated_at?: string | null;
};

export async function fetchSystemSettings() {
  const { data } = await opsRequest<SystemSettingRecord[]>("/settings");
  return data;
}

export async function updateSystemSettings(settings: Record<string, unknown>) {
  const { data, message } = await opsRequest<Record<string, unknown>>(
    "/settings",
    {
      method: "PUT",
      body: JSON.stringify({ settings }),
    },
  );

  return { data, message };
}

export async function fetchFeatureFlags() {
  const { data } = await opsRequest<FeatureFlagRecord[]>("/feature-flags");
  return data;
}

export async function updateFeatureFlags(flags: Record<string, boolean>) {
  const { data, message } = await opsRequest<Record<string, boolean>>(
    "/feature-flags",
    {
      method: "PUT",
      body: JSON.stringify({ flags }),
    },
  );

  return { data, message };
}
