import type { ReactElement } from "react";
import { render, type RenderOptions } from "@testing-library/react";
import { ToastProvider } from "@/components/ui/ToastProvider";

export function renderWithProviders(
  ui: ReactElement,
  options?: Omit<RenderOptions, "wrapper">,
) {
  return render(ui, {
    wrapper: ({ children }) => <ToastProvider>{children}</ToastProvider>,
    ...options,
  });
}
