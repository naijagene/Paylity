"use client";

import { useSearchParams } from "next/navigation";
import { useEffect, useState } from "react";
import { Button } from "@/components/Button";
import { PageContainer } from "@/components/PageContainer";
import {
  fetchFeatureFlags,
  fetchSystemSettings,
  updateFeatureFlags,
  updateSystemSettings,
  type FeatureFlagRecord,
  type SystemSettingRecord,
} from "@/lib/api/admin";
import { ApiError, ApiOfflineError } from "@/lib/api/client";
import { useToast } from "@/components/ui/ToastProvider";

export function PlatformClient() {
  const searchParams = useSearchParams();
  const initialTab = searchParams.get("tab") === "flags" ? "flags" : "settings";
  const [tab, setTab] = useState<"settings" | "flags">(initialTab);
  const [settings, setSettings] = useState<SystemSettingRecord[]>([]);
  const [flags, setFlags] = useState<FeatureFlagRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { showToast } = useToast();

  useEffect(() => {
    let cancelled = false;

    Promise.all([fetchSystemSettings(), fetchFeatureFlags()])
      .then(([settingsData, flagsData]) => {
        if (!cancelled) {
          setSettings(settingsData);
          setFlags(flagsData);
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(
            err instanceof ApiOfflineError
              ? "Network unavailable."
              : err instanceof ApiError
                ? err.message
                : "Unable to load platform settings.",
          );
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const handleSettingChange = (key: string, value: string) => {
    setSettings((current) =>
      current.map((setting) => {
        if (setting.key !== key) {
          return setting;
        }

        if (setting.type === "boolean") {
          return { ...setting, value: value === "true" };
        }

        if (setting.type === "integer") {
          return { ...setting, value: Number(value) };
        }

        return { ...setting, value };
      }),
    );
  };

  const handleFlagChange = (key: string, enabled: boolean) => {
    setFlags((current) =>
      current.map((flag) => (flag.key === key ? { ...flag, enabled } : flag)),
    );
  };

  const handleSaveSettings = async () => {
    setSaving(true);
    setError(null);

    try {
      const payload = Object.fromEntries(
        settings.map((setting) => [setting.key, setting.value]),
      );
      await updateSystemSettings(payload);
      showToast({ title: "Settings updated", variant: "success" });
      setSettings(await fetchSystemSettings());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to save settings.");
    } finally {
      setSaving(false);
    }
  };

  const handleSaveFlags = async () => {
    setSaving(true);
    setError(null);

    try {
      const payload = Object.fromEntries(
        flags.map((flag) => [flag.key, Boolean(flag.enabled)]),
      );
      await updateFeatureFlags(payload);
      showToast({ title: "Feature flags updated", variant: "success" });
      setFlags(await fetchFeatureFlags());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to save feature flags.");
    } finally {
      setSaving(false);
    }
  };

  const maintenanceMode = settings.find((setting) => setting.key === "maintenance_mode");
  const incidentMode = settings.find((setting) => setting.key === "incident_mode");

  const toggleMaintenanceMode = async () => {
    if (!maintenanceMode) {
      return;
    }

    const nextValue = !Boolean(maintenanceMode.value);
    setSaving(true);

    try {
      await updateSystemSettings({ maintenance_mode: nextValue });
      showToast({
        title: nextValue ? "Maintenance mode enabled" : "Maintenance mode disabled",
        variant: "success",
      });
      setSettings(await fetchSystemSettings());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to update maintenance mode.");
    } finally {
      setSaving(false);
    }
  };

  const toggleIncidentMode = async () => {
    if (!incidentMode) {
      return;
    }

    const nextValue = !Boolean(incidentMode.value);
    setSaving(true);

    try {
      await updateSystemSettings({ incident_mode: nextValue });
      showToast({
        title: nextValue ? "Incident mode enabled" : "Incident mode disabled",
        variant: "success",
      });
      setSettings(await fetchSystemSettings());
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Unable to update incident mode.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <PageContainer className="py-8" narrow={false}>
      <div className="mx-auto w-full max-w-5xl space-y-6">
        <header>
          <h1 className="font-display text-3xl font-extrabold text-dark">Platform</h1>
          <p className="mt-2 text-sm text-muted">
            Manage launch settings, feature flags, and maintenance mode.
          </p>
        </header>

        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant={tab === "settings" ? "primary" : "outline"}
            onClick={() => setTab("settings")}
          >
            System Settings
          </Button>
          <Button
            type="button"
            variant={tab === "flags" ? "primary" : "outline"}
            onClick={() => setTab("flags")}
          >
            Feature Flags
          </Button>
        </div>

        {maintenanceMode ? (
          <section className="rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="font-display text-lg font-extrabold text-dark">Maintenance Mode</h2>
                <p className="mt-1 text-sm text-muted">
                  Temporarily disable checkout while performing platform maintenance.
                </p>
              </div>
              <Button
                type="button"
                variant={Boolean(maintenanceMode.value) ? "secondary" : "outline"}
                onClick={() => void toggleMaintenanceMode()}
                disabled={saving}
              >
                {Boolean(maintenanceMode.value) ? "Disable Maintenance" : "Enable Maintenance"}
              </Button>
            </div>
          </section>
        ) : null}

        {incidentMode ? (
          <section className="rounded-2xl border border-red-200 bg-red-50 p-5">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 className="font-display text-lg font-extrabold text-dark">Incident Mode</h2>
                <p className="mt-1 text-sm text-muted">
                  Pause checkout and show the customer-facing incident banner during platform incidents.
                </p>
              </div>
              <Button
                type="button"
                variant={Boolean(incidentMode.value) ? "secondary" : "outline"}
                onClick={() => void toggleIncidentMode()}
                disabled={saving}
              >
                {Boolean(incidentMode.value) ? "Disable Incident Mode" : "Enable Incident Mode"}
              </Button>
            </div>
          </section>
        ) : null}

        {error ? (
          <p className="rounded-2xl border border-error/20 bg-error/5 px-4 py-3 text-sm text-error">
            {error}
          </p>
        ) : null}

        {loading ? (
          <p className="text-sm text-muted">Loading platform configuration…</p>
        ) : tab === "settings" ? (
          <section className="space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
            {settings.map((setting) => (
              <label key={setting.key} className="block border-b border-dark/5 pb-4 last:border-b-0">
                <span className="font-semibold text-dark">{setting.key}</span>
                {setting.description ? (
                  <span className="mt-1 block text-xs text-muted">{setting.description}</span>
                ) : null}
                {setting.type === "boolean" ? (
                  <select
                    value={Boolean(setting.value) ? "true" : "false"}
                    onChange={(event) => handleSettingChange(setting.key, event.target.value)}
                    className="mt-3 w-full rounded-2xl border border-border px-4 py-3 text-sm outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
                  >
                    <option value="true">Enabled</option>
                    <option value="false">Disabled</option>
                  </select>
                ) : (
                  <input
                    value={String(setting.value ?? "")}
                    onChange={(event) => handleSettingChange(setting.key, event.target.value)}
                    className="mt-3 w-full rounded-2xl border border-border px-4 py-3 text-sm outline-none focus-visible:border-success focus-visible:ring-2 focus-visible:ring-success/20"
                  />
                )}
              </label>
            ))}
            <Button type="button" onClick={() => void handleSaveSettings()} disabled={saving}>
              {saving ? "Saving…" : "Save Settings"}
            </Button>
          </section>
        ) : (
          <section className="space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
            {flags.map((flag) => (
              <label
                key={flag.key}
                className="flex items-start justify-between gap-4 border-b border-dark/5 pb-4 last:border-b-0"
              >
                <span>
                  <span className="block font-semibold text-dark">{flag.key}</span>
                  {flag.description ? (
                    <span className="mt-1 block text-xs text-muted">{flag.description}</span>
                  ) : null}
                  {flag.enabled !== flag.stored_enabled ? (
                    <span className="mt-1 block text-xs text-amber-700">
                      Effective value may be overridden by environment configuration.
                    </span>
                  ) : null}
                </span>
                <input
                  type="checkbox"
                  checked={Boolean(flag.enabled)}
                  onChange={(event) => handleFlagChange(flag.key, event.target.checked)}
                  className="mt-1 h-5 w-5 rounded border-border text-success focus-visible:ring-success"
                  aria-label={`Toggle ${flag.key}`}
                />
              </label>
            ))}
            <Button type="button" onClick={() => void handleSaveFlags()} disabled={saving}>
              {saving ? "Saving…" : "Save Feature Flags"}
            </Button>
          </section>
        )}
      </div>
    </PageContainer>
  );
}
