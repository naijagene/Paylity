import Link from "next/link";
import { PageContainer } from "./PageContainer";

type PlaceholderPageProps = {
  title: string;
};

export function PlaceholderPage({ title }: PlaceholderPageProps) {
  return (
    <main className="flex flex-1 flex-col">
      <PageContainer className="flex flex-1 flex-col items-start justify-center py-16">
        <Link
          href="/"
          className="mb-8 text-sm font-medium text-foreground/60 transition-colors hover:text-primary"
        >
          ← Back to home
        </Link>
        <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
          {title}
        </h1>
        <p className="mt-3 text-lg text-foreground/60">Coming next</p>
      </PageContainer>
    </main>
  );
}
