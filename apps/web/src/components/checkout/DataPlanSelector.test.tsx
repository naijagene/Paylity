import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { DataPlanSelector } from "./DataPlanSelector";
import type { CatalogDataPlan } from "@/lib/checkout/catalogPlans";

const basePlans: CatalogDataPlan[] = [
  {
    variationCode: "plan-expensive",
    serviceId: "mtn-data",
    network: "MTN",
    name: "5GB - 30 Days",
    displayName: "5GB - 30 Days",
    providerName: "MTN Data - 5000 Naira - 5GB - 30 days",
    price: 5000,
    fixedPrice: true,
    isPopular: false,
    validityLabel: "30 Days",
    dataSizeLabel: "5GB",
    sortOrder: 30005000,
  },
  {
    variationCode: "plan-cheap",
    serviceId: "mtn-data",
    network: "MTN",
    name: "500MB - 30 Days",
    displayName: "500MB - 30 Days",
    providerName: "MTN Data - 500 Naira - 500MB - 30 days",
    price: 500,
    fixedPrice: true,
    isPopular: true,
    validityLabel: "30 Days",
    dataSizeLabel: "500MB",
    sortOrder: 30000500,
  },
];

describe("DataPlanSelector", () => {
  it("renders display_name and provider name when different", () => {
    render(
      <DataPlanSelector
        network="MTN"
        selectedPlanId=""
        plans={basePlans}
        onChange={() => undefined}
      />,
    );

    expect(screen.getByText("500MB - 30 Days")).toBeInTheDocument();
    expect(
      screen.getByText("MTN Data - 500 Naira - 500MB - 30 days"),
    ).toBeInTheDocument();
  });

  it("does not render hidden plans passed from API", () => {
    render(
      <DataPlanSelector
        network="MTN"
        selectedPlanId=""
        plans={basePlans}
        onChange={() => undefined}
      />,
    );

    expect(screen.queryByText(/Xtratalk/i)).not.toBeInTheDocument();
  });

  it("shows no-plan message when list is empty", () => {
    render(
      <DataPlanSelector
        network="MTN"
        selectedPlanId=""
        plans={[]}
        onChange={() => undefined}
      />,
    );

    expect(
      screen.getByText("No data plans are currently available for this network."),
    ).toBeInTheDocument();
  });

  it("renders badges for popular and metadata labels", () => {
    render(
      <DataPlanSelector
        network="MTN"
        selectedPlanId="plan-cheap"
        plans={[basePlans[1]!]}
        onChange={() => undefined}
      />,
    );

    expect(screen.getByText("Popular")).toBeInTheDocument();
    expect(screen.getByText("500MB")).toBeInTheDocument();
    expect(screen.getByText("30 Days")).toBeInTheDocument();
    expect(screen.getByText("₦500")).toBeInTheDocument();
  });
});
