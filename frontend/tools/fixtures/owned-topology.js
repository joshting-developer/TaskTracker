import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const root = new URL('../../../', import.meta.url).pathname;
const profiles = JSON.parse(readFileSync(new URL('../../../tools/regression-profiles.json', import.meta.url), 'utf8'));

function selectedCompose(profile) {
  assert.equal(process.env.E2E_ISOLATED, '1');
  const target = new URL(process.env.E2E_API_URL);
  assert.equal(target.protocol, 'http:');
  assert.equal(target.hostname, '127.0.0.1');
  assert.ok(Number(target.port) >= 13000 && Number(target.port) < 15000, 'Synthetic test API port required');
  const external = process.env.E2E_COMPOSE_FILE;
  const agent = process.env.E2E_AGENT;
  if (agent === 'codex' || agent === 'claude') {
    const expected = profile ? profiles[profile].apiPort + (agent === 'claude' ? 100 : 0) : (agent === 'claude' ? 13198 : 13199);
    assert.equal(Number(target.port), expected, 'Test backend belongs to another owner');
  }
  if (external) assert.ok(['codex', 'claude'].includes(agent), 'Explicit owner required for external test configuration');
  const project = process.env.E2E_COMPOSE_PROJECT || (external ? `tasktracker-${agent}-${profile ? 'regression' : 'test'}` : 'tasktracker-tests');
  const args = ['compose', '--env-file', '/dev/null', '-p', project, '--project-directory', root, '-f', external || 'docker-compose.yml'];
  if (!external) args.push('--profile', 'standalone');
  const database = external ? `sp_tasktracker_${agent}_${profile || 'test'}` : 'tasktracker_test';
  return { args, agent: agent || 'standalone', database,
    username: external ? 'sp_' + createHash('sha256').update(database).digest('hex').slice(0, 24) : 'tasktracker_test' };
}

export function ownedDefaultTopology() {
  const topology = selectedCompose();
  return {
    ...topology,
    command: (...command) => ['docker', [...topology.args, ...command]],
    phpFile: (...argsForPhp) => execFileSync('docker', [...topology.args, 'exec', '-T', 'backend', 'php', ...argsForPhp], { cwd: root, env: process.env, encoding: 'utf8' }),
  };
}

export function ownedTopology(profile) {
  assert.equal(process.env.E2E_REGRESSION_PROFILE, profile);
  const config = profiles[profile];
  assert.ok(config, 'Known isolated profile required');
  const topology = selectedCompose(profile);
  const service = process.env.E2E_COMPOSE_FILE ? config.service : 'backend';
  return {
    ...config, ...topology, service,
    websocketPort: Number(process.env.E2E_WS_PORT || (13209 + (topology.agent === 'claude' ? 100 : 0))),
    command: (...command) => ['docker', [...topology.args, ...command]],
    php: code => execFileSync('docker', [...topology.args, 'exec', '-T', service, 'php', '-r', code], { cwd: root, env: process.env, stdio: 'pipe' }),
  };
}
