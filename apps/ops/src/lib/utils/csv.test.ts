import { describe, expect, it, vi } from "vitest";
import { exportCsv } from "@/lib/utils/csv";

describe("exportCsv", () => {
  it("creates a downloadable csv blob", () => {
    const click = vi.fn();
    const createObjectURL = vi.fn(() => "blob:mock");
    const revokeObjectURL = vi.fn();

    vi.stubGlobal("URL", {
      createObjectURL,
      revokeObjectURL,
    });

    const anchor = {
      href: "",
      download: "",
      click,
    } as unknown as HTMLAnchorElement;

    vi.spyOn(document, "createElement").mockReturnValue(anchor);

    exportCsv("report.csv", [
      ["Reference", "Status"],
      ["PAY-001", "fulfilled"],
    ]);

    expect(createObjectURL).toHaveBeenCalledOnce();
    expect(anchor.download).toBe("report.csv");
    expect(anchor.href).toBe("blob:mock");
    expect(click).toHaveBeenCalledOnce();
    expect(revokeObjectURL).toHaveBeenCalledWith("blob:mock");
  });
});
