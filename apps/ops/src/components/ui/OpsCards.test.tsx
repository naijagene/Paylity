import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { KpiCard } from "@/components/ui/OpsCards";

describe("KpiCard", () => {
  it("renders label and value", () => {
    render(<KpiCard label="Today's Revenue" value="₦12,500" />);

    expect(screen.getByText("Today's Revenue")).toBeInTheDocument();
    expect(screen.getByText("₦12,500")).toBeInTheDocument();
  });
});
