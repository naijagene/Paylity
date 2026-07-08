import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";
import pngToIco from "png-to-ico";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const publicDir = path.resolve(__dirname, "../public");
const sourceSvg = path.join(publicDir, "brand/paylity-mark.svg");

const pngTargets = [
  { name: "favicon-16x16.png", size: 16 },
  { name: "favicon-32x32.png", size: 32 },
  { name: "apple-touch-icon.png", size: 180 },
  { name: "android-chrome-192x192.png", size: 192 },
  { name: "android-chrome-512x512.png", size: 512 },
];

const svgBuffer = await readFile(sourceSvg);

for (const target of pngTargets) {
  const outputPath = path.join(publicDir, target.name);
  const png = await sharp(svgBuffer)
    .resize(target.size, target.size, { fit: "contain", background: "#0f172a" })
    .png()
    .toBuffer();

  await writeFile(outputPath, png);
  console.log(`Wrote ${target.name}`);
}

const favicon16 = await sharp(svgBuffer).resize(16, 16).png().toBuffer();
const favicon32 = await sharp(svgBuffer).resize(32, 32).png().toBuffer();
const faviconIco = await pngToIco([favicon16, favicon32]);
await writeFile(path.join(publicDir, "favicon.ico"), faviconIco);
console.log("Wrote favicon.ico");

const manifest = {
  name: "PAYLITY NG",
  short_name: "PAYLITY",
  description: "Buy airtime, data, and electricity securely in seconds.",
  start_url: "/",
  display: "standalone",
  background_color: "#0f172a",
  theme_color: "#10b981",
  icons: [
    {
      src: "/android-chrome-192x192.png",
      sizes: "192x192",
      type: "image/png",
      purpose: "any",
    },
    {
      src: "/android-chrome-512x512.png",
      sizes: "512x512",
      type: "image/png",
      purpose: "any",
    },
    {
      src: "/android-chrome-512x512.png",
      sizes: "512x512",
      type: "image/png",
      purpose: "maskable",
    },
  ],
};

await writeFile(
  path.join(publicDir, "site.webmanifest"),
  `${JSON.stringify(manifest, null, 2)}\n`,
);
console.log("Wrote site.webmanifest");
