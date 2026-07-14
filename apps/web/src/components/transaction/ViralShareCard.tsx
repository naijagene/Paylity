"use client";

import { Button } from "@/components/Button";
import { buildShareLinks, trackTransactionShare } from "@/lib/api/reviews";

type ViralShareCardProps = {
  reference: string;
  pageUrl: string;
};

export function ViralShareCard({ reference, pageUrl }: ViralShareCardProps) {
  const links = buildShareLinks(reference, pageUrl);

  async function handleShare(channel: keyof typeof links | "copy_link") {
    await trackTransactionShare(reference, channel);

    if (channel === "copy_link") {
      await navigator.clipboard.writeText(`${pageUrl}\n${links.whatsapp.replace("https://wa.me/?text=", "")}`);
      return;
    }

    window.open(links[channel], "_blank", "noopener,noreferrer");
  }

  return (
    <section className="rounded-2xl border border-border bg-card p-5 shadow-sm">
      <h2 className="text-lg font-bold text-dark">Share PAYLITY</h2>
      <p className="mt-1 text-sm text-muted">
        Tell friends about the free airtime campaign and help PAYLITY grow.
      </p>
      <div className="mt-4 grid gap-2 sm:grid-cols-2">
        <Button type="button" variant="outline" onClick={() => void handleShare("whatsapp")}>
          WhatsApp
        </Button>
        <Button type="button" variant="outline" onClick={() => void handleShare("facebook")}>
          Facebook
        </Button>
        <Button type="button" variant="outline" onClick={() => void handleShare("telegram")}>
          Telegram
        </Button>
        <Button type="button" variant="outline" onClick={() => void handleShare("x")}>
          X
        </Button>
        <Button type="button" variant="secondary" className="sm:col-span-2" onClick={() => void handleShare("copy_link")}>
          Copy Link
        </Button>
      </div>
    </section>
  );
}
