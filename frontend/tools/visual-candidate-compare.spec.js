import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';
import policy from './visual/policy.json' with { type: 'json' };

// Compare every candidate, including pages after an expected UI change, without updating goldens.
const root = resolve(import.meta.dirname, '../../artifacts/visual');
const manifest = JSON.parse(readFileSync(`${root}/candidate/manifest.json`, 'utf8'));
for (const image of manifest.files) {
  test(image.path, async () => {
    const actual = readFileSync(`${root}/candidate/${image.path}`);
    expect(actual).toMatchSnapshot(image.path.split('/'), { threshold: policy.threshold, maxDiffPixels: policy.maxDiffPixels });
  });
}
