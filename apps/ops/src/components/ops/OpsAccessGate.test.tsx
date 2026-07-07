import { fireEvent, screen } from "@testing-library/react";
import { describe, expect, it, vi, beforeEach } from "vitest";
import { OpsAccessGate } from "@/components/ops/OpsAccessGate";
import { renderWithProviders } from "@/test/renderWithProviders";
import * as operatorKey from "@/lib/ops/operatorKey";

vi.mock("@/components/layout/OpsShell", () => ({
  OpsShell: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="ops-shell">{children}</div>
  ),
}));

describe("OpsAccessGate", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    localStorage.clear();
  });

  it("shows unlock form when operator key is missing", () => {
    vi.spyOn(operatorKey, "hasOperatorKey").mockReturnValue(false);

    renderWithProviders(
      <OpsAccessGate>
        <p>Protected content</p>
      </OpsAccessGate>,
    );

    expect(screen.getByText("PAYLITY Operations Console")).toBeInTheDocument();
    expect(screen.queryByText("Protected content")).not.toBeInTheDocument();
  });

  it("unlocks the console when a key is submitted", () => {
    let authenticated = false;

    vi.spyOn(operatorKey, "hasOperatorKey").mockImplementation(() => authenticated);
    vi.spyOn(operatorKey, "setOperatorKey").mockImplementation((key) => {
      authenticated = true;
      localStorage.setItem("paylity.operatorKey", key);
    });

    renderWithProviders(
      <OpsAccessGate>
        <p>Protected content</p>
      </OpsAccessGate>,
    );

    fireEvent.change(screen.getByLabelText("Operator access key"), {
      target: { value: "secret-key" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Unlock Console" }));

    expect(operatorKey.setOperatorKey).toHaveBeenCalledWith("secret-key");
    expect(screen.getByTestId("ops-shell")).toBeInTheDocument();
    expect(screen.getByText("Protected content")).toBeInTheDocument();
  });
});
