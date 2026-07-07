import { describe, expect, it } from "vitest";
import {
  calculateSuccessRate,
  healthLabel,
  mapApiHealth,
  mapDatabaseHealth,
  mapFeatureHealth,
} from "@/lib/utils/health";

describe("health utils", () => {
  it("maps database health statuses", () => {
    expect(mapDatabaseHealth("ok")).toBe("healthy");
    expect(mapDatabaseHealth("failed")).toBe("offline");
    expect(mapDatabaseHealth(undefined)).toBe("warning");
  });

  it("maps api health statuses", () => {
    expect(mapApiHealth("ok")).toBe("healthy");
    expect(mapApiHealth("degraded")).toBe("warning");
    expect(mapApiHealth("down")).toBe("offline");
  });

  it("maps feature flag health", () => {
    expect(mapFeatureHealth(true)).toBe("healthy");
    expect(mapFeatureHealth(false)).toBe("warning");
  });

  it("calculates success rate", () => {
    expect(calculateSuccessRate(80, 100)).toBe("80%");
    expect(calculateSuccessRate(0, 0)).toBe("—");
  });

  it("returns readable health labels", () => {
    expect(healthLabel("healthy")).toBe("Healthy");
    expect(healthLabel("warning")).toBe("Warning");
    expect(healthLabel("offline")).toBe("Offline");
  });
});
