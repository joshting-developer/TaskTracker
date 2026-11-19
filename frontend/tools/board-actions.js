// 使用真實看板互動；保留所在Epic泳道，只把卡片拖到指定狀態欄。
// 放下後卡片會先移到目標欄；預設再等送出完成（無送出中卡片），settle:false供刻意攔住寫入的測試。
export async function moveBoardCard(page, identifier, state, { settle = true } = {}) {
  const card = page.locator(`.issue-card[data-issue="${identifier}"]`);
  const lane = page.locator('.board-lane').filter({ has: card });
  const target = lane.locator(`[data-column="${state}"]`);
  // 模擬HTML5拖放偶爾在版面位移時漏掉drop事件（未送出任何寫入）；重新量測後最多再試一次。
  for (let attempt = 0; attempt < 2; attempt++) {
    await card.scrollIntoViewIfNeeded();
    // TT-437：手機寬度捲動後版面還會移動一小段, 太快量位置會把卡片放錯地方；等兩個畫格位置不變再量。
    const [sourceBox, targetBox] = await settledBoxes(page, card, target);
    // 長欄位在卡片旁邊落下，不要求拖回欄位最上方。
    const y = Math.max(5, Math.min(targetBox.height - 5, sourceBox.y + 10 - targetBox.y));
    await card.dragTo(target, { sourcePosition: { x: 10, y: 10 }, targetPosition: { x: 10, y } });
    try { await target.locator(`[data-issue="${identifier}"]`).waitFor({ timeout: attempt ? 15000 : 4000 }); }
    catch (failure) { if (attempt) throw failure; continue; }
    if (settle) await page.locator('.issue-card.moving').waitFor({ state: 'detached', timeout: 15000 });
    return;
  }
}

async function settledBoxes(page, ...locators) {
  let previous = '';
  for (let frame = 0; frame < 30; frame++) {
    await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
    const boxes = await Promise.all(locators.map(locator => locator.boundingBox()));
    const key = JSON.stringify(boxes);
    if (key === previous) return boxes;
    previous = key;
  }
  return JSON.parse(previous);
}

// 手機／鍵盤替代途徑：進入詳細頁，用既有狀態操作後返回來源看板。
export async function changeBoardCardStateInDetail(page, identifier, state) {
  await page.locator(`.issue-card[data-issue="${identifier}"] .card-title`).press('Enter');
  const detail = page.getByRole('region', { name: '任務完整頁', exact: true });
  const control = detail.getByRole('group', { name: '任務狀態', exact: true });
  const select = control.getByRole('combobox');
  const label = await select.locator(`option[value="${state}"]`).textContent();
  await select.selectOption(state);
  await control.locator('.status-badge').filter({ hasText: label }).waitFor();
  await detail.getByRole('button', { name: '返回看板', exact: true }).click();
  await page.locator(`[data-column="${state}"] [data-issue="${identifier}"]`).waitFor();
}
