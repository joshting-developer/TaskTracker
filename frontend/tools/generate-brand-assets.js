/** All raster and tile variants come from the committed SVG master. */
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import { chromium } from 'playwright';

const folder = new URL('../public-assets/brand/', import.meta.url);
const master = await readFile(new URL('tasktracker-mark.svg', folder), 'utf8');
if (/<(?:script|foreignObject|image|use)\b|(?:href|onload|onclick)=/i.test(master)) throw new Error('Unsafe SVG source');
const geometry = master.match(/<g\b[\s\S]*<\/g>/)?.[0];
if (!geometry) throw new Error('Missing brand geometry');
// ViewBox padding keeps the rotated CSS bars intact. Tiles use the same mark.
const nested = `<svg x="0" y="0" width="100" height="100" viewBox="-2 -3 25 28">${geometry}</svg>`;
const tile = (scale, radius = 18) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" rx="${radius}" fill="#23242b"/><g transform="translate(${(100 - scale) / 2} ${(100 - scale) / 2}) scale(${scale / 100})">${nested}</g></svg>\n`;
const favicon = tile(74);
await mkdir(folder, { recursive: true });
await writeFile(new URL('tasktracker-favicon-v1.svg', folder), favicon);
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
const records = [];
try {
  const page = await browser.newPage();
  async function render(name, size, svg) {
    const base64 = await page.evaluate(async ({ svg, size }) => {
      const image = new Image(); image.src = 'data:image/svg+xml;base64,' + btoa(svg); await image.decode();
      const canvas = document.createElement('canvas'); canvas.width = canvas.height = size;
      const ratio = Math.min(size / image.naturalWidth, size / image.naturalHeight);
      const width = image.naturalWidth * ratio, height = image.naturalHeight * ratio;
      canvas.getContext('2d').drawImage(image, (size - width) / 2, (size - height) / 2, width, height); return canvas.toDataURL('image/png').split(',')[1];
    }, { svg, size });
    const bytes = Buffer.from(base64, 'base64'); await writeFile(new URL(name, folder), bytes);
    records.push({ file: name, width: size, height: size, sha256: createHash('sha256').update(bytes).digest('hex') }); return bytes;
  }
  const favicons = [];
  for (const size of [16, 32, 48]) favicons.push(await render(`tasktracker-favicon-v1-${size}.png`, size, favicon));
  const icoHeader = Buffer.alloc(6 + 16 * favicons.length); icoHeader.writeUInt16LE(1, 2); icoHeader.writeUInt16LE(favicons.length, 4);
  let offset = icoHeader.length;
  favicons.forEach((bytes, index) => {
    const at = 6 + 16 * index, size = [16, 32, 48][index];
    icoHeader[at] = icoHeader[at + 1] = size; icoHeader.writeUInt16LE(1, at + 4); icoHeader.writeUInt16LE(32, at + 6);
    icoHeader.writeUInt32LE(bytes.length, at + 8); icoHeader.writeUInt32LE(offset, at + 12); offset += bytes.length;
  });
  await writeFile(new URL('favicon-v1.ico', folder), Buffer.concat([icoHeader, ...favicons]));
  await render('tasktracker-apple-v1-180.png', 180, tile(68, 0));
  for (const size of [192, 512]) await render(`tasktracker-app-v1-${size}.png`, size, tile(68, 0));
  await render('tasktracker-maskable-v1-512.png', 512, tile(50, 0));
  // Transparent exports are useful without introducing another vector source.
  for (const size of [64, 128, 256, 512, 1024]) await render(`tasktracker-mark-v1-${size}.png`, size, master);
  await writeFile(new URL('assets.json', folder), JSON.stringify({ master: 'tasktracker-mark.svg',
    masterSha256: createHash('sha256').update(master).digest('hex'), color: '#ab9cf3', rotation: -8,
    maskable: { markBounds: [0.25, 0.25, 0.75, 0.75], safeRadius: 0.4 }, images: records }, null, 2) + '\n');
  console.log(`Generated ${records.length} PNGs, one multi-size ICO and SVG favicon from ${fileURLToPath(new URL('tasktracker-mark.svg', folder))}`);
} finally { await browser.close(); }
