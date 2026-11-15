import assert from 'node:assert/strict';
import { readFile, writeFile, mkdir, readdir, copyFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { chromium } from 'playwright';
const root = new URL('../../', import.meta.url), folder = new URL('../public-assets/brand/', import.meta.url);
const out = new URL('../../artifacts/tt362/', import.meta.url), approved = new URL('../../.local/visual-baselines/brand-assets/', import.meta.url);
const approvalFile = 'docs/visual-baselines/brand-assets-darwin-arm64.json';
const mode = process.argv[2] ?? '--candidate'; assert.ok(['--candidate', '--approve', '--compare'].includes(mode));
const sha = bytes => createHash('sha256').update(bytes).digest('hex');
const manifest = JSON.parse(await readFile(new URL('assets.json', folder)));
const master = await readFile(new URL(manifest.master, folder)); assert.equal(sha(master), manifest.masterSha256);
assert.doesNotMatch(master.toString(), /<(script|image|foreignObject|use)\b|href=|onload=/i);
assert.match(master.toString(), /rotate\(-8 10.5 11.5\)/); assert.equal((master.toString().match(/<rect /g) ?? []).length, 3);
const ico = await readFile(new URL('favicon-v1.ico', folder)); assert.equal(ico.readUInt16LE(2), 1); assert.equal(ico.readUInt16LE(4), 3);
for (const [index, size] of [16, 32, 48].entries()) {
  const at = 6 + index * 16; assert.equal(ico[at], size); assert.equal(ico[at + 1], size);
  const bytes = ico.subarray(ico.readUInt32LE(at + 12), ico.readUInt32LE(at + 12) + ico.readUInt32LE(at + 8));
  assert.deepEqual(bytes, await readFile(new URL(`tasktracker-favicon-v1-${size}.png`, folder)));
}
await mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' });
const screenshots = [];
try {
  const page = await browser.newPage({ deviceScaleFactor: 1 }); const images = [];
  for (const image of manifest.images) {
    const bytes = await readFile(new URL(image.file, folder)); assert.equal(sha(bytes), image.sha256);
    const src = 'data:image/png;base64,' + bytes.toString('base64');
    const decoded = await page.evaluate(async ({ src }) => {
      const image = new Image(); image.src = src; await image.decode();
      const canvas = document.createElement('canvas'); canvas.width = image.naturalWidth; canvas.height = image.naturalHeight;
      const ctx = canvas.getContext('2d'); ctx.drawImage(image, 0, 0); const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
      let mark = 0, maxRadius = 0;
      for (let i = 0; i < pixels.length; i += 4) if (pixels[i] > 100 && pixels[i + 1] > 80 && pixels[i + 2] > 150 && pixels[i + 3] > 200) {
        mark++; const p = i / 4; maxRadius = Math.max(maxRadius, Math.hypot((p % canvas.width + .5) / canvas.width - .5, (Math.floor(p / canvas.width) + .5) / canvas.height - .5));
      }
      return { width: canvas.width, height: canvas.height, mark, maxRadius };
    }, { src });
    assert.equal(decoded.width, image.width); assert.equal(decoded.height, image.height); assert.ok(decoded.mark > 4, image.file);
    if (image.file.includes('maskable')) assert.ok(decoded.maxRadius < .4, 'Maskable pixels exceed safe circle');
    images.push({ ...image, src });
  }
  const iconData = 'data:image/x-icon;base64,' + ico.toString('base64');
  assert.equal(await page.evaluate(async src => { const i = new Image(); i.src = src; await i.decode(); return i.naturalWidth; }, iconData), 48);
  const cells = [16, 32, 48].flatMap(size => ['#fff', '#23242b'].map(color => {
    const image = images.find(i => i.file === `tasktracker-favicon-v1-${size}.png`);
    return `<figure style="background:${color};color:${color === '#fff' ? '#23242b' : '#fff'}"><figcaption>${size} px</figcaption><img alt="TaskTracker ${size} pixel icon" width="${size}" height="${size}" src="${image.src}"><img alt="Enlarged pixel view" class="zoom" width="${size * 4}" height="${size * 4}" src="${image.src}"></figure>`;
  })).join('');
  const gallery = `<html lang="en"><style>*{box-sizing:border-box}body{margin:0;padding:20px;font:16px Arial;background:#eee}h1{font-size:20px;margin:0 0 16px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(250px,100%),1fr));gap:16px}figure{margin:0;border:1px solid #ccc;padding:16px;display:flex;align-items:center;gap:16px;min-height:260px;flex-wrap:wrap}figcaption{width:100%}.zoom{image-rendering:pixelated;max-width:100%;height:auto}</style><h1>TaskTracker brand assets</h1><div class="grid">${cells}</div></html>`;
  await page.close();
  for (const width of [1440, 768, 390]) {
    const page = await browser.newPage({ viewport: { width, height: 900 }, deviceScaleFactor: 1 });
    await page.setContent(gallery);
    await page.locator('img').evaluateAll(images => Promise.all(images.map(i => i.decode())));
    await page.evaluate(async () => { await document.fonts.ready; await new Promise(requestAnimationFrame); await new Promise(requestAnimationFrame); });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    const name = `assets-${width}.png`, path = new URL(name, out); await page.screenshot({ path: path.pathname, fullPage: true });
    screenshots.push({ file: name, sha256: sha(await readFile(path)) });
    await page.close();
  }
} finally { await browser.close(); }
const sourcePaths = (await readdir(folder)).sort().map(name => 'frontend/public-assets/brand/' + name);
sourcePaths.push('frontend/tools/generate-brand-assets.js', 'frontend/tools/check-brand-assets.js');
const sources = await Promise.all(sourcePaths.map(async path => ({ path, sha256: sha(await readFile(new URL(path, root))) })));
const canonicalSources = '[' + sources.map(row => '{\"path\": ' + JSON.stringify(row.path) + ', \"sha256\": ' + JSON.stringify(row.sha256) + '}').join(', ') + ']';
const sourceFingerprint = sha(canonicalSources);
const harnessSha256 = sha(await readFile(new URL(import.meta.url)));
const manifestSha256 = sha(JSON.stringify(screenshots));
if (mode === '--approve') {
  await mkdir(approved, { recursive: true });
  for (const row of screenshots) await copyFile(new URL(row.file, out), new URL(row.file, approved));
  await writeFile(new URL(approvalFile, root), JSON.stringify({ approved: true, reviewer: 'Codex under Josh standing approval',
    scope: 'TT-362 asset gallery only; product UI and deployment are TT-363', manifestSha256, harnessSha256, screenshots }, null, 2) + '\n');
}
let comparisonPassed = false;
if (mode === '--compare') {
  const approval = JSON.parse(await readFile(new URL(approvalFile, root))); assert.equal(approval.approved, true); assert.equal(approval.harnessSha256, harnessSha256);
  for (const row of screenshots) { const expected = approval.screenshots.find(x => x.file === row.file); assert.equal(row.sha256, expected.sha256); assert.equal(sha(await readFile(new URL(row.file, approved))), expected.sha256); }
  comparisonPassed = true;
}
const summary = { status: comparisonPassed ? 'passed' : 'candidate', approved: comparisonPassed, approvalFile, manifestSha256, harnessSha256,
  sourceRoots: ['frontend/public-assets/brand'], sourceFiles: sources, sourceFingerprint, screenshots, imageCount: manifest.images.length,
  scope: 'Synthetic asset gallery; no product pages changed' };
await writeFile(new URL('asset-summary.json', out), JSON.stringify(summary, null, 2));
console.log(`${mode}: ${manifest.images.length} PNGs, ICO frames, maskable safe circle and three gallery widths passed`);
