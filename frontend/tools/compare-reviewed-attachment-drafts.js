import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, rmSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
const require = createRequire(import.meta.url);
// Decoder comes from the lockfile-pinned Playwright distribution, only in this test tool.
const { PNG } = require('playwright-core/lib/utilsBundle');
const root = resolve(import.meta.dirname, '../..');
rmSync(resolve(root, 'artifacts/tt282/visual-comparison.json'), { force: true });
const read = path => readFileSync(resolve(root, path));
const hash = bytes => createHash('sha256').update(bytes).digest('hex');
const approval = JSON.parse(read('docs/visual-baselines/tt282-attachment-drafts.json'));
const manifestPath = 'docs/evidence/tt282-visual-manifest.json';
assert.equal(approval.approved, true);
assert.equal(hash(read(manifestPath)), approval.manifestSha256);
const manifest = JSON.parse(read(manifestPath));
for (const file of manifest.harness) assert.equal(hash(read(file.path)), file.sha256, `Changed harness: ${file.path}`);
const policy = JSON.parse(read('frontend/tools/visual/policy.json'));
assert.deepEqual(policy, manifest.policy, 'Comparison policy changed');
const rows = [];
for (const file of manifest.images) {
  const baseline = read(`artifacts/tt282/approved/${file.name}`);
  assert.equal(hash(baseline), file.sha256, `Missing or changed reviewed image: ${file.name}`);
  const before = PNG.sync.read(baseline), after = PNG.sync.read(read(file.source));
  assert.equal(after.width, before.width); assert.equal(after.height, before.height);
  let differentPixels = 0, rawDifferences = 0;
  for (let n = 0; n < before.data.length; n += 4) {
    assert.equal(before.data[n + 3], 255); assert.equal(after.data[n + 3], 255);
    const r = before.data[n] - after.data[n], g = before.data[n + 1] - after.data[n + 1], b = before.data[n + 2] - after.data[n + 2];
    if (r || g || b) rawDifferences++;
    const y = r * .29889531 + g * .58662247 + b * .11448223;
    const i = r * .59597799 - g * .2741761 - b * .32180189;
    const q = r * .21147017 - g * .52261711 + b * .31114694;
    // Pixelmatch YIQ distance, same policy threshold; no anti-alias exclusion or masks.
    if (.5053 * y * y + .299 * i * i + .1957 * q * q > 35215 * policy.threshold ** 2) differentPixels++;
  }
  assert.ok(differentPixels <= policy.maxDiffPixels, `${file.name}: ${differentPixels} changed pixels`);
  rows.push({ name: file.name, differentPixels, rawDifferences });
}
writeFileSync(resolve(root, 'artifacts/tt282/visual-comparison.json'), JSON.stringify({ status: 'passed', approved: true, manifestSha256: approval.manifestSha256, policy, images: rows }, null, 2));
console.log(`PASS: ${rows.length} reviewed images, existing threshold ${policy.threshold}, zero differing pixels, no masks`);
