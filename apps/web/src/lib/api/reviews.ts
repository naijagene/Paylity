import { apiRequest } from "./client";

export async function submitTransactionReview(
  reference: string,
  rating: number,
  comment?: string,
) {
  return apiRequest(`/transactions/${encodeURIComponent(reference)}/review`, {
    method: "POST",
    body: JSON.stringify({ rating, comment }),
  });
}

export async function trackTransactionShare(reference: string, channel: string) {
  return apiRequest(`/transactions/${encodeURIComponent(reference)}/share`, {
    method: "POST",
    body: JSON.stringify({ channel }),
  });
}

export function buildCampaignShareMessage(): string {
  return "I just got free airtime on PAYLITY and only paid the service charges. Try PAYLITY for fast airtime, data and electricity payments.";
}

export function getCampaignLandingUrl(): string {
  if (typeof window !== "undefined") {
    return `${window.location.origin}/airtime`;
  }

  return "https://paylity.ng/airtime";
}

export function buildShareLinks(pageUrl: string) {
  const text = encodeURIComponent(buildCampaignShareMessage());
  const url = encodeURIComponent(pageUrl);

  return {
    whatsapp: `https://wa.me/?text=${text}%20${url}`,
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${url}&quote=${text}`,
    telegram: `https://t.me/share/url?url=${url}&text=${text}`,
    x: `https://twitter.com/intent/tweet?text=${text}&url=${url}`,
  };
}
