import type { Metadata } from "next";
import { Inter, Poppins } from "next/font/google";
import { OpsAccessGate } from "@/components/ops/OpsAccessGate";
import { Providers } from "./providers";
import "./globals.css";

const inter = Inter({
  variable: "--font-inter",
  subsets: ["latin"],
});

const poppins = Poppins({
  variable: "--font-poppins",
  subsets: ["latin"],
  weight: ["600", "700", "800"],
});

export const metadata: Metadata = {
  title: "PAYLITY Operations Console",
  description: "Minimum viable operations console for PAYLITY soft launch.",
  robots: { index: false, follow: false },
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en" className={`${inter.variable} ${poppins.variable} h-full antialiased`}>
      <body className="flex min-h-full flex-col bg-background text-foreground">
        <Providers>
          <OpsAccessGate>{children}</OpsAccessGate>
        </Providers>
      </body>
    </html>
  );
}
