import { ReceiptVerificationClient } from "@/components/verify/ReceiptVerificationClient";

type VerifyPageProps = {
  params: Promise<{ token: string }>;
};

export default async function VerifyPage({ params }: VerifyPageProps) {
  const { token } = await params;

  return <ReceiptVerificationClient token={decodeURIComponent(token)} />;
}
