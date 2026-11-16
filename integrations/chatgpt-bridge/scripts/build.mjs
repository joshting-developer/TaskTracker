import { readFile, mkdir, writeFile } from 'node:fs/promises';
const contract = await readFile(new URL('../worker/contract.js', import.meta.url), 'utf8');
const source = await readFile(new URL('../worker/index.js', import.meta.url), 'utf8');
const hosting = JSON.parse(await readFile(new URL('../.openai/hosting.json', import.meta.url), 'utf8'));
if (!hosting.project_id || !hosting.capabilities?.includes('mcp')) throw new Error('Missing Site identity or MCP capability');
const dist = new URL('../dist/', import.meta.url);
// Both outputs have fixed names; overwrite them without deleting protected directories.
await mkdir(new URL('server/', dist), { recursive: true });
await mkdir(new URL('.openai/', dist), { recursive: true });
await writeFile(new URL('server/index.js', dist), source.replace("import { TOOLS } from './contract.js';", contract.replace('export const TOOLS', 'const TOOLS')));
await writeFile(new URL('.openai/hosting.json', dist), JSON.stringify(hosting, null, 2));
console.log('Built single dependency-free Worker module');
