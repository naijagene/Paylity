import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

vi.mock("@/components/go-live/GoLiveClient", () => ({
  GoLiveClient: () => <div>Go Live Page</div>,
}));

import GoLivePage from "@/app/go-live/page";

describe("GoLivePage", () => {
  it("renders the go-live client", () => {
    render(<GoLivePage />);
    expect(screen.getByText("Go Live Page")).toBeInTheDocument();
  });
});
