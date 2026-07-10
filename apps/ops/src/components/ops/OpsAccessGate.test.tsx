import { fireEvent, screen, waitFor } from "@testing-library/react";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { OpsAccessGate } from "@/components/ops/OpsAccessGate";
import { OperatorAuthProvider } from "@/lib/ops/OperatorAuthProvider";
import { renderWithProviders } from "@/test/renderWithProviders";
import * as operatorAuthApi from "@/lib/api/operatorAuth";
import * as operatorKey from "@/lib/ops/operatorKey";

vi.mock("@/components/layout/OpsShell", () => ({
  OpsShell: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="ops-shell">{children}</div>
  ),
}));

function renderGate() {
  return renderWithProviders(
    <OperatorAuthProvider>
      <OpsAccessGate>
        <p>Protected content</p>
      </OpsAccessGate>
    </OperatorAuthProvider>,
  );
}

describe("OpsAccessGate", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    sessionStorage.clear();
  });

  it("shows unlock form when operator key is missing", async () => {
    renderGate();

    await waitFor(() => {
      expect(screen.getByText("PAYLITY Operations Console")).toBeInTheDocument();
    });

    expect(screen.queryByText("Protected content")).not.toBeInTheDocument();
  });

  it("unlocks the console when the API validates the key", async () => {
    vi.spyOn(operatorAuthApi, "validateOperatorAccess").mockResolvedValue({
      authenticated: true,
      role: "operator",
    });

    renderGate();

    await waitFor(() => {
      expect(screen.getByLabelText("Operator access key")).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText("Operator access key"), {
      target: { value: "test-operator-key" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Unlock Console" }));

    await waitFor(() => {
      expect(operatorAuthApi.validateOperatorAccess).toHaveBeenCalledWith(
        "test-operator-key",
      );
      expect(screen.getByTestId("ops-shell")).toBeInTheDocument();
      expect(screen.getByText("Protected content")).toBeInTheDocument();
    });
  });

  it("does not unlock the console for a wrong key", async () => {
    const { ApiError } = await import("@/lib/api/client");
    const setOperatorKeySpy = vi.spyOn(operatorKey, "setOperatorKey");

    vi.spyOn(operatorAuthApi, "validateOperatorAccess").mockRejectedValue(
      new ApiError("Invalid or missing operator access key.", {
        code: "OPERATOR_ACCESS_DENIED",
      }, 401),
    );

    renderGate();

    await waitFor(() => {
      expect(screen.getByLabelText("Operator access key")).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText("Operator access key"), {
      target: { value: "wrong-key" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Unlock Console" }));

    await waitFor(() => {
      expect(screen.getByText("Invalid operator access key.")).toBeInTheDocument();
    });

    expect(setOperatorKeySpy).not.toHaveBeenCalled();
    expect(screen.queryByText("Protected content")).not.toBeInTheDocument();
  });

  it("does not call the API for a blank key", async () => {
    const validateSpy = vi.spyOn(operatorAuthApi, "validateOperatorAccess");

    renderGate();

    await waitFor(() => {
      expect(screen.getByLabelText("Operator access key")).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole("button", { name: "Unlock Console" }));

    await waitFor(() => {
      expect(screen.getByText("Enter a valid operator access key.")).toBeInTheDocument();
    });

    expect(validateSpy).not.toHaveBeenCalled();
  });

  it("revalidates a stored key before rendering protected content", async () => {
    sessionStorage.setItem("paylity-operator-key", "test-operator-key");

    const validateSpy = vi
      .spyOn(operatorAuthApi, "validateOperatorAccess")
      .mockResolvedValue({
        authenticated: true,
        role: "operator",
      });

    renderGate();

    await waitFor(() => {
      expect(validateSpy).toHaveBeenCalledWith("test-operator-key");
      expect(screen.getByTestId("ops-shell")).toBeInTheDocument();
    });
  });

  it("clears an invalid stored key", async () => {
    sessionStorage.setItem("paylity-operator-key", "stale-key");

    const { ApiError } = await import("@/lib/api/client");
    vi.spyOn(operatorAuthApi, "validateOperatorAccess").mockRejectedValue(
      new ApiError("Invalid or missing operator access key.", {
        code: "OPERATOR_ACCESS_DENIED",
      }, 401),
    );

    renderGate();

    await waitFor(() => {
      expect(screen.getByText("Invalid operator access key.")).toBeInTheDocument();
      expect(sessionStorage.getItem("paylity-operator-key")).toBeNull();
      expect(screen.queryByText("Protected content")).not.toBeInTheDocument();
    });
  });

  it("does not unlock on network failure", async () => {
    const setOperatorKeySpy = vi.spyOn(operatorKey, "setOperatorKey");
    const { ApiOfflineError } = await import("@/lib/api/client");

    vi.spyOn(operatorAuthApi, "validateOperatorAccess").mockRejectedValue(
      new ApiOfflineError(),
    );

    renderGate();

    await waitFor(() => {
      expect(screen.getByLabelText("Operator access key")).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText("Operator access key"), {
      target: { value: "test-operator-key" },
    });
    fireEvent.click(screen.getByRole("button", { name: "Unlock Console" }));

    await waitFor(() => {
      expect(
        screen.getByText(
          "PAYLITY API is currently unavailable. Check your connection and try again.",
        ),
      ).toBeInTheDocument();
    });

    expect(setOperatorKeySpy).not.toHaveBeenCalled();
    expect(screen.queryByText("Protected content")).not.toBeInTheDocument();
  });
});
