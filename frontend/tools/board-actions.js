// 使用真實看板互動；保留所在Epic泳道，只把卡片拖到指定狀態欄。
export async function moveBoardCard(page, identifier, state) {
  const card = page.locator(`.issue-card[data-issue="${identifier}"]`);
  const lane = page.locator('.board-lane').filter({ has: card });
  const target = lane.locator(`[data-column="${state}"]`);
  // 模擬HTML5拖放偶爾在版面位移時漏掉drop事件（未送出任何寫入）；重新量測後最多再試一次。
  for (let attempt = 0; attempt < 2; attempt++) {
    await card.scrollIntoViewIfNeeded();
    const sourceBox = await card.boundingBox(), targetBox = await target.boundingBox();
    // 長欄位在卡片旁邊落下，不要求拖回欄位最上方。
    const y = Math.max(5, Math.min(targetBox.height - 5, sourceBox.y + 10 - targetBox.y));
    await card.dragTo(target, { sourcePosition: { x: 10, y: 10 }, targetPosition: { x: 10, y } });
    try { await target.locator(`[data-issue="${identifier}"]`).waitFor({ timeout: attempt ? 15000 : 4000 }); return; }
    catch (failure) { if (attempt) throw failure; }
  }
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
