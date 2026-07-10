import { screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { ProfileClient } from "@/components/profile/ProfileClient";
import { renderWithProviders } from "@/test/renderWithProviders";

vi.mock("@/lib/ops/OperatorAuthProvider", () => ({
  useOperatorAuth: () => ({
    status: "authenticated",
    error: null,
    unlock: vi.fn(),
    lock: vi.fn(),
    isAuthenticated: true,
  }),
}));

describe("ProfileClient", () => {
  it("does not display or copy the operator key", () => {
    renderWithProviders(<ProfileClient />);

    expect(screen.getByText("Operator session authenticated.")).toBeInTheDocument();
    expect(screen.getByText("Authenticated")).toBeInTheDocument();
    expect(screen.queryByText("Copy Key")).not.toBeInTheDocument();
    expect(screen.queryByText(/•/)).not.toBeInTheDocument();
  });
});
