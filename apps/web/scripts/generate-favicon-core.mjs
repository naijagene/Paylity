import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import sharp from "sharp";
import pngToIco from "png-to-ico";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const publicDir = path.resolve(__dirname, "../public");
const sourceSvg = path.join(publicDir, "brand/paylity-mark.svg");

const svgBuffer = await readFile(sourceSvg);

async function writePng(name, size) {
  const png = await sharp(svgBuffer)
    .resize(size, size, { fit: "contain", background: "#0f172a" })
    .png()
    .toBuffer();

  await writeFile(path.join(publicDir, name), png);
  console.log(`Wrote ${name} (${png.length} bytes)`);
  return png;
}

await writePng("favicon-16x16.png", 16);
const favicon32 = await writePng("favicon-32x32.png", 32);
const favicon16 = await sharp(svgBuffer).resize(16, 16).png().toBuffer();
const faviconIco = await pngToIco([favicon16, favicon32]);
await writeFile(path.join(publicDir, "favicon.ico"), faviconIco);
console.log(`Wrote favicon.ico (${faviconIco.length} bytes)`);
