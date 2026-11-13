/** Open the same disclosure users use before editing secondary Issue fields. */
export async function expandIssueProperties(dialog) {
  const properties = dialog.locator('.issue-properties').first();
  await properties.waitFor({ state: 'attached' });
  if (!await properties.evaluate(element => element.open)) await properties.locator('summary').first().click();
}
