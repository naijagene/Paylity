import { screen } from "@testing-library/react";
import { renderWithProviders } from "@/test/renderWithProviders";
import { afterEach, describe, expect, it, vi } from "vitest";
import { SupportCard } from "./SupportCard";

describe("SupportCard", () => {
  afterEach(() => {
    vi.unstubAllEnvs();
  });

  it("shows email support", () => {
    vi.stubEnv("NEXT_PUBLIC_WHATSAPP_URL", "");

    renderWithProviders(<SupportCard reference="PYL-20260702-ABC123" />);

    expect(
      screen.getByRole("link", {
        name: /Email PAYLITY Support at support@paylity.ng/i,
      }),
    ).toBeInTheDocument();
    expect(screen.getByText("support@paylity.ng")).toBeInTheDocument();
  });

  it("shows WhatsApp link when configured", () => {
    vi.stubEnv(
      "NEXT_PUBLIC_WHATSAPP_URL",
      "https://wa.me/2348012345678",
    );

    renderWithProviders(<SupportCard reference="PYL-20260702-ABC123" />);

    expect(
      screen.getByRole("link", { name: /Chat with PAYLITY Support on WhatsApp/i }),
    ).toBeInTheDocument();
    expect(screen.getByText("Chat on WhatsApp")).toBeInTheDocument();
  });

  it("shows WhatsApp coming soon card when URL is not configured", () => {
    vi.stubEnv("NEXT_PUBLIC_WHATSAPP_URL", "");

    renderWithProviders(<SupportCard reference="PYL-20260702-ABC123" />);

    expect(
      screen.getByLabelText(/WhatsApp Support coming soon/i),
    ).toBeInTheDocument();
    expect(screen.getByText("Coming Soon")).toBeInTheDocument();
    expect(screen.getByText("WhatsApp Support")).toBeInTheDocument();
  });

  it("shows WhatsApp coming soon card when placeholder number is configured", () => {
    vi.stubEnv(
      "NEXT_PUBLIC_WHATSAPP_URL",
      "https://wa.me/2348000000000",
    );

    renderWithProviders(<SupportCard reference="PYL-20260702-ABC123" />);

    expect(
      screen.getByLabelText(/WhatsApp Support coming soon/i),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("link", { name: /Chat with PAYLITY Support on WhatsApp/i }),
    ).not.toBeInTheDocument();
  });

  it("shows transaction reference with copy action", () => {
    vi.stubEnv("NEXT_PUBLIC_WHATSAPP_URL", "");

    renderWithProviders(<SupportCard reference="PYL-20260702-ABC123" />);

    expect(screen.getByText("PYL-20260702-ABC123")).toBeInTheDocument();
    expect(
      screen.getByRole("button", {
        name: /Copy Reference PYL-20260702-ABC123/i,
      }),
    ).toBeInTheDocument();
  });
});
