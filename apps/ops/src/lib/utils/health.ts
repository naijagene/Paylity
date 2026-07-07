export type HealthIndicator = "healthy" | "warning" | "offline";

export function mapDatabaseHealth(status?: string): HealthIndicator {
  if (status === "ok") {
    return "healthy";
  }

  if (status === "failed") {
    return "offline";
  }

  return "warning";
}

export function mapFeatureHealth(enabled: boolean): HealthIndicator {
  return enabled ? "healthy" : "warning";
}

export function mapApiHealth(status?: string): HealthIndicator {
  if (status === "ok") {
    return "healthy";
  }

  if (status === "degraded") {
    return "warning";
  }

  return "offline";
}

export function healthLabel(indicator: HealthIndicator): string {
  switch (indicator) {
    case "healthy":
      return "Healthy";
    case "warning":
      return "Warning";
    case "offline":
      return "Offline";
  }
}

export function healthClasses(indicator: HealthIndicator): string {
  switch (indicator) {
    case "healthy":
      return "border-success/20 bg-success/10 text-success";
    case "warning":
      return "border-amber-200 bg-amber-50 text-amber-700";
    case "offline":
      return "border-error/20 bg-error/10 text-error";
  }
}

export function calculateSuccessRate(
  successful: number,
  total: number,
): string {
  if (total <= 0) {
    return "—";
  }

  return `${Math.round((successful / total) * 100)}%`;
}
