import { beforeEach, describe, expect, it, vi } from "vitest";
import { ApiError } from "@/lib/api/client";
import { downloadOpsFile, downloadVoucherCsv } from "@/lib/api/ops";

const fetchMock = vi.fn();
const createObjectURLMock = vi.fn(() => "blob:mock-url");
const revokeObjectURLMock = vi.fn();
const clickMock = vi.fn();

vi.stubGlobal("fetch", fetchMock);

describe("downloadOpsFile", () => {
  beforeEach(() => {
    fetchMock.mockReset();
    createObjectURLMock.mockClear();
    revokeObjectURLMock.mockClear();
    clickMock.mockClear();
    sessionStorage.setItem("paylity-operator-key", "test-operator-key");
    vi.spyOn(URL, "createObjectURL").mockImplementation(createObjectURLMock);
    vi.spyOn(URL, "revokeObjectURL").mockImplementation(revokeObjectURLMock);
    vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(clickMock);
  });

  it("includes the operator authentication header", async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      blob: async () => new Blob(["code,status\nABC,reserved"], { type: "text/csv" }),
      headers: new Headers(),
    });

    await downloadOpsFile("/ops/marketing/vouchers/export.csv", "paylity-voucher-usage.csv");

    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining("/ops/marketing/vouchers/export.csv"),
      expect.objectContaining({
        headers: expect.objectContaining({
          "X-Operator-Key": "test-operator-key",
        }),
      }),
    );
    expect(fetchMock.mock.calls[0]?.[0]).not.toContain("test-operator-key");
  });

  it("uses Content-Disposition filename when provided", async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      blob: async () => new Blob(["csv"], { type: "text/csv" }),
      headers: new Headers({
        "Content-Disposition": 'attachment; filename="server-export.csv"',
      }),
    });

    await downloadOpsFile("/ops/marketing/vouchers/export.csv", "fallback.csv");

    const anchor = clickMock.mock.contexts[0] as HTMLAnchorElement;
    expect(anchor.download).toBe("server-export.csv");
  });

  it("uses fallback filename when Content-Disposition is absent", async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      blob: async () => new Blob(["csv"], { type: "text/csv" }),
      headers: new Headers(),
    });

    await downloadOpsFile("/ops/marketing/vouchers/export.csv", "paylity-voucher-usage-4.csv");

    const anchor = clickMock.mock.contexts[0] as HTMLAnchorElement;
    expect(anchor.download).toBe("paylity-voucher-usage-4.csv");
  });

  it("creates and clicks a download anchor for successful blob responses", async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      blob: async () => new Blob(["csv"], { type: "text/csv" }),
      headers: new Headers(),
    });

    await downloadOpsFile("/ops/marketing/vouchers/export.csv", "paylity-voucher-usage.csv");

    expect(createObjectURLMock).toHaveBeenCalledTimes(1);
    expect(clickMock).toHaveBeenCalledTimes(1);
  });

  it("revokes the object URL after download", async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      blob: async () => new Blob(["csv"], { type: "text/csv" }),
      headers: new Headers(),
    });

    await downloadOpsFile("/ops/marketing/vouchers/export.csv", "paylity-voucher-usage.csv");

    expect(revokeObjectURLMock).toHaveBeenCalledWith("blob:mock-url");
  });

  it("throws a readable ApiError for 401 JSON responses", async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 401,
      headers: new Headers({ "content-type": "application/json" }),
      json: async () => ({
        success: false,
        message: "Invalid or missing operator access key.",
        errors: { code: "OPERATOR_ACCESS_DENIED" },
      }),
    });

    await expect(downloadOpsFile("/ops/marketing/vouchers/export.csv", "paylity-voucher-usage.csv")).rejects.toMatchObject({
      message: "Invalid or missing operator access key.",
      status: 401,
    } satisfies Partial<ApiError>);
  });
});

describe("downloadVoucherCsv", () => {
  beforeEach(() => {
    fetchMock.mockReset();
    sessionStorage.setItem("paylity-operator-key", "test-operator-key");
    vi.spyOn(URL, "createObjectURL").mockImplementation(() => "blob:mock-url");
    vi.spyOn(URL, "revokeObjectURL").mockImplementation(() => undefined);
    vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => undefined);
  });

  it("requests campaign-specific export URLs with campaign_id", async () => {
    fetchMock.mockResolvedValue({
      ok: true,
      blob: async () => new Blob(["csv"], { type: "text/csv" }),
      headers: new Headers(),
    });

    await downloadVoucherCsv(4);

    expect(fetchMock.mock.calls[0]?.[0]).toContain("/ops/marketing/vouchers/export.csv?campaign_id=4");
  });

  it("does not use direct navigation helpers", async () => {
    const openSpy = vi.spyOn(window, "open").mockImplementation(() => null);
    fetchMock.mockResolvedValue({
      ok: true,
      blob: async () => new Blob(["csv"], { type: "text/csv" }),
      headers: new Headers(),
    });

    await downloadVoucherCsv(4);

    expect(openSpy).not.toHaveBeenCalled();
    openSpy.mockRestore();
  });
});
