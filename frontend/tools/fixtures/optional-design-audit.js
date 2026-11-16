import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

// Product tests run without an Agent installation; an explicit opt-in adds the external audit.
export async function loadDesignAudit() {
  const root = process.env.DESIGN_TASTE_SKILL_ROOT;
  if (!root) return null;
  const probe = await import(pathToFileURL(resolve(root, 'scripts/lib/probe.mjs')).href);
  const rules = await import(pathToFileURL(resolve(root, 'scripts/lib/rules.mjs')).href);
  return { ...probe, ...rules };
}
