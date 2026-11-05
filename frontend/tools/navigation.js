// 工作空間首頁與專案列表分開；透過實際導覽，不繞過草稿保護。
export async function openProjectList(page) {
  await page.locator('.app-shell').waitFor();
  async function showNavigation() {
    const toggle = page.getByRole('button', { name: '開啟導覽', exact: true });
    if (await toggle.isVisible() && await toggle.getAttribute('aria-expanded') !== 'true') await toggle.click();
  }
  if (await page.getByLabel('目前專案', { exact: true }).count()) {
    await showNavigation();
    await page.getByRole('link', { name: 'TaskTracker 返回首頁', exact: true }).click();
  }
  await showNavigation();
  await page.getByRole('button', { name: '專案列表', exact: true }).click();
}
