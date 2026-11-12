import { defineConfig } from '@playwright/test';
import { resolve } from 'node:path';
export default defineConfig({
  testDir: './tools',
  testMatch: 'visual-candidate-compare.spec.js',
  workers: 1,
  retries: 0,
  updateSnapshots: 'none',
  snapshotPathTemplate: `${resolve(import.meta.dirname, '../artifacts/visual/approved-darwin-arm64')}/{arg}{ext}`,
  outputDir: '../artifacts/tt264/baseline-diff',
  reporter: [['list'], ['json', { outputFile: '../artifacts/tt264/baseline-diff.json' }]],
});
