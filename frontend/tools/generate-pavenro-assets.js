/** Export the approved Season 1 SVGs without changing their geometry. */
import assert from 'node:assert/strict';
import { readFile, writeFile, mkdir, copyFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { chromium } from 'playwright';
const source = new URL('../../design/season1/pavenro/', import.meta.url);
const output = new URL('../public-assets/brand/', import.meta.url);
await mkdir(output, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
const images = [];
try {
  const page = await browser.newPage();
  for (const name of ['pavenro-mark-source.svg', 'pavenro-logo-horizontal.svg', 'pavenro-logo-horizontal-inverse.svg', 'pavenro-favicon-16.svg']) {
    await copyFile(new URL(name, source), new URL(name, output));
  }
  const exports = [
    ...[16, 32, 48].map(size => [`pavenro-favicon-${size}.svg`, `pavenro-v1-${size}.png`, size, size]),
    ['pavenro-apple-touch-180.svg', 'pavenro-apple-v1-180.png', 180, 180],
    ...[192, 512].map(size => [`pavenro-app-icon-${size}.svg`, `pavenro-app-v1-${size}.png`, size, size]),
    ['pavenro-og-1200x630.svg', 'pavenro-og-v1.png', 1200, 630],
  ];
  for (const [input, file, width, height] of exports) {
    const svg = await readFile(new URL(input, source), 'utf8');
    assert.doesNotMatch(svg, /<(?:script|foreignObject|image|use)\b|(?:href|onload|onclick)=/i);
    const bytes = Buffer.from(await page.evaluate(async ({src,width,height}) => {
      const image = new Image(); image.src = src; await image.decode();
      const canvas = document.createElement('canvas'); canvas.width = width; canvas.height = height;
      canvas.getContext('2d').drawImage(image,0,0,width,height);
      return canvas.toDataURL('image/png').split(',')[1];
    }, {src: 'data:image/svg+xml;base64,' + Buffer.from(svg).toString('base64'), width, height}), 'base64');
    await writeFile(new URL(file, output), bytes);
    images.push({file,width,height,source:input,sha256:createHash('sha256').update(bytes).digest('hex')});
  }
  const header=Buffer.alloc(6+3*16);header.writeUInt16LE(1,2);header.writeUInt16LE(3,4);
  const frames=[];let offset=header.length;
  for(const [index,size] of [16,32,48].entries()) {
    const bytes=await readFile(new URL(`pavenro-v1-${size}.png`,output));frames.push(bytes);
    const at=6+index*16;header[at]=header[at+1]=size;header.writeUInt16LE(1,at+4);header.writeUInt16LE(32,at+6);header.writeUInt32LE(bytes.length,at+8);header.writeUInt32LE(offset,at+12);offset+=bytes.length;
  }
  await writeFile(new URL('pavenro-v1.ico',output),Buffer.concat([header,...frames]));
  const icon = await readFile(new URL('pavenro-app-v1-512.png', output));
  const maskable = Buffer.from(await page.evaluate(async src => {
    const image = new Image(); image.src = src; await image.decode();
    const canvas = document.createElement('canvas'); canvas.width = canvas.height = 512;
    const ctx = canvas.getContext('2d'); ctx.fillStyle = '#4338CA'; ctx.fillRect(0,0,512,512);
    ctx.drawImage(image,128,128,256,256);
    return canvas.toDataURL('image/png').split(',')[1];
  }, 'data:image/png;base64,' + icon.toString('base64')), 'base64');
  await writeFile(new URL('pavenro-maskable-v1-512.png', output), maskable);
  images.push({file:'pavenro-maskable-v1-512.png',width:512,height:512,source:'pavenro-app-icon-512.svg',sha256:createHash('sha256').update(maskable).digest('hex')});
  await writeFile(new URL('pavenro-assets.json',output),JSON.stringify({images},null,2)+'\n');
  console.log(`Exported ${images.length} approved Pavenro PNGs, SVGs and ICO`);
} finally { await browser.close(); }
