import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const root = new URL('../../../', import.meta.url).pathname;
const profiles = JSON.parse(readFileSync(new URL('../../../tools/regression-profiles.json', import.meta.url), 'utf8'));

export function ownedDefaultTopology() {
  const targets = { 'http://127.0.0.1:13199': 'codex', 'http://127.0.0.1:13198': 'claude' };
  const agent = targets[process.env.E2E_API_URL];
  assert.equal(process.env.E2E_ISOLATED, '1');
  assert.ok(agent, 'Only an Agent-owned isolated backend is allowed');
  if (process.env.E2E_AGENT) assert.equal(process.env.E2E_AGENT, agent);
  const args = ['compose', '--env-file', '/dev/null', '-p', `tasktracker-${agent}-test`, '-f', 'docker-compose.mysql-test.yml'];
  return {
    agent,
    command: (...command) => ['docker', [...args, ...command]],
    phpFile: (...argsForPhp) => execFileSync('docker', [...args, 'exec', '-T', 'backend', 'php', ...argsForPhp], { cwd: root, env: process.env, encoding: 'utf8' }),
  };
}

// Test-only SQL/process access stays pinned to our named Compose and synthetic schemas.
export function ownedTopology(profile) {
  const agent = process.env.E2E_AGENT;
  assert.ok(['codex', 'claude'].includes(agent), 'Explicit test owner required');
  assert.equal(process.env.E2E_ISOLATED, '1');
  assert.equal(process.env.E2E_REGRESSION_PROFILE, profile);
  const config = profiles[profile];
  assert.ok(config, 'Known isolated profile required');
  const port = config.apiPort + (agent === 'claude' ? 100 : 0);
  assert.equal(process.env.E2E_API_URL, `http://127.0.0.1:${port}`);
  const args = ['compose', '--env-file', '/dev/null', '-p', `tasktracker-${agent}-regression`, '-f', 'docker-compose.regression.yml'];
  return {
    ...config,
    websocketPort: 13209 + (agent === 'claude' ? 100 : 0),
    command: (...command) => ['docker', [...args, ...command]],
    php: code => execFileSync('docker', [...args, 'exec', '-T', config.service, 'php', '-r', code], { cwd: root, env: process.env, stdio: 'pipe' }),
  };
}
