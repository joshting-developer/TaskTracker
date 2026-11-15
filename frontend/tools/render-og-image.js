// TT-208：以HTML繪製1200×630社群分享圖並輸出public-assets/og-image.png；內容與/about/一致，不含真實資料。
import { chromium } from 'playwright';
import { readFile } from 'node:fs/promises';
const mark = Buffer.from(await readFile(new URL('../public-assets/brand/tasktracker-mark.svg', import.meta.url))).toString('base64');
const html = `<!doctype html><html lang="zh-Hant"><body style="margin:0;width:1200px;height:630px;background:#202127;font-family:-apple-system,'Noto Sans TC',sans-serif;color:#f6f5fa;display:flex;align-items:center;padding:0 80px;box-sizing:border-box;gap:56px">
<div style="flex:1"><div style="display:flex;align-items:center;gap:14px;font-size:34px;font-weight:750"><img src="data:image/svg+xml;base64,${mark}" width="36" height="40" alt="" />TaskTracker</div>
<h1 style="font-size:46px;line-height:1.35;margin:36px 0 22px;letter-spacing:-1px">任務看板、個人待辦與工時<br>放在同一個工作空間</h1>
<p style="font-size:26px;color:#cfc8dc;margin:0">專案任務 · Epic 看板 · 交辦 · 每日工時</p></div>
<div style="width:420px;height:400px;background:#fff;border-radius:18px;padding:24px;box-sizing:border-box;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;color:#4e485c;font-size:16px">
${['待規劃','進行中','已關閉'].map((name, column) => `<div style="background:#f3f1f9;border-radius:12px;padding:12px"><b>${name}</b>${Array.from({ length: [2, 1, 1][column] }, () => `<div style="height:74px;background:#fff;border:1px solid #e3def0;border-radius:10px;margin-top:12px"></div>`).join('')}</div>`).join('')}</div></body></html>`;
const browser = await chromium.launch({ headless: true, ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) });
const page = await browser.newPage({ locale: 'zh-TW', viewport: { width: 1200, height: 630 } });
await page.setContent(html);
await page.locator('img').evaluateAll(images => Promise.all(images.map(image => image.decode())));
await page.evaluate(() => document.fonts.ready);
await page.screenshot({ path: new URL('../public-assets/og-image.png', import.meta.url).pathname });
await browser.close();
console.log('wrote public-assets/og-image.png');
