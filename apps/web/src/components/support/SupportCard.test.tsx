import { render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { SupportCard } from "./SupportCard";

describe("SupportCard", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
  });

  it("shows email support", () => {
    vi.stubEnv("NEXT_PUBLIC_WHATSAPP_URL", "");

    render(<SupportCard reference="PYL-20260702-ABC123" />);

    expect(
      screen.getByRole("link", {
        name: /Email PAYLITY Support at support@paylity.ng/i,
      }),
    ).toBeInTheDocument();
    expect(screen.getByText("support@paylity.ng")).toBeInTheDocument();
  });

  it("shows WhatsApp support only when configured", () => {
    vi.stubEnv(
      "NEXT_PUBLIC_WHATSAPP_URL",
      "https://wa.me/2348012345678",
    );

    render(<SupportCard reference="PYL-20260702-ABC123" />);

    expect(
      screen.getByRole("link", { name: /Chat with PAYLITY Support on WhatsApp/i }),
    ).toBeInTheDocument();
  });

  it("hides WhatsApp support when placeholder number is configured", () => {
    vi.stubEnv(
      "NEXT_PUBLIC_WHATSAPP_URL",
      "https://wa.me/2348000000000",
    );

    render(<SupportCard reference="PYL-20260702-ABC123" />);

    expect(
      screen.queryByRole("link", { name: /WhatsApp Support/i }),
    ).not.toBeInTheDocument();
  });
});
