import { defineConfig } from '@playwright/test';
import screenshotPolicy from './tools/visual/policy.json' with { type: 'json' };
import { resolve } from 'node:path';

// The wrapper verifies approval and chooses candidate or immutable approved snapshots.
const snapshots = process.env.VISUAL_SNAPSHOT_DIR;
const origin = process.env.TASKTRACKER_VISUAL_ORIGIN;
if (origin !== 'http://127.0.0.1:5195') throw new Error('Use the dedicated visual frontend origin');
if (!snapshots || process.env.E2E_API_URL !== 'http://127.0.0.1:13079') throw new Error('Use tools/visual-check.sh with the isolated visual API');
export default defineConfig({
  testDir: './tools/visual',
  testMatch: 'flows.spec.js',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 180000,
  outputDir: process.env.VISUAL_RESULTS_DIR || '../artifacts/visual/test-results',
  snapshotPathTemplate: `${resolve(snapshots)}/{projectName}/{arg}{ext}`,
  reporter: [['list'], ['json', { outputFile: process.env.VISUAL_REPORT_FILE || resolve(import.meta.dirname, '../artifacts/visual/playwright-report.json') }]],
  expect: { timeout: 10000, toHaveScreenshot: screenshotPolicy },
  use: { baseURL: origin, locale: 'zh-TW', timezoneId: 'Asia/Taipei', deviceScaleFactor: 1, colorScheme: 'light', reducedMotion: 'reduce', trace: 'off', screenshot: 'only-on-failure', launchOptions: { args: ['--disable-gpu'], ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) } },
  projects: [
    { name: 'desktop', use: { viewport: { width: 1440, height: 900 } } },
    { name: 'tablet', use: { viewport: { width: 768, height: 1024 } } },
    { name: 'mobile', use: { viewport: { width: 390, height: 844 } } },
  ],
  webServer: { cwd: import.meta.dirname, command: 'npm run dev -- --port 5195', url: origin, reuseExistingServer: false, env: { API_PROXY_TARGET: process.env.E2E_API_URL } },
});
