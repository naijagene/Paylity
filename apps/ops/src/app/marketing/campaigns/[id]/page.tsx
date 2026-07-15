import { CampaignDetailClient } from "@/components/marketing/CampaignDetailClient";

type CampaignDetailPageProps = {
  params: Promise<{ id: string }>;
};

export default async function CampaignDetailPage({ params }: CampaignDetailPageProps) {
  const { id } = await params;
  return <CampaignDetailClient campaignId={Number(id)} />;
}
