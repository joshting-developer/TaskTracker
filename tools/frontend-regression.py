#!/usr/bin/env python3
"""Run local synthetic checks by topology; never read production env or reset a DB."""
import argparse
import base64
import json
import os
from pathlib import Path
import re
import secrets
import signal
import socket
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
PROFILES = json.loads((ROOT / 'tools/regression-profiles.json').read_text())
SPECIAL = {
    'policy': ['registration-policy'], 'inline': ['inline-mail'],
    'pagination': ['issue-pagination'], 'seed': ['time-analysis-seed'],
    'realtime': ['realtime'], 'r2': ['r2-attachment'], 'runtime': ['production-smoke'],
}


def cases(group):
    if group in SPECIAL:
        return SPECIAL[group]
    excluded = {name for names in SPECIAL.values() for name in names}
    return [p.stem.removesuffix('-check') for p in sorted((ROOT / 'frontend/tools').glob('*-check.js'))
            if p.stem.removesuffix('-check') not in excluded]


def require_free_port(port):
    with socket.socket() as probe:
        probe.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        try:
            probe.bind(('127.0.0.1', port))
        except OSError:
            raise SystemExit(f'Frontend port {port} is occupied; wait for its owner, do not stop it')


def run(command, env, log, *, cwd=ROOT, stdin=None, timeout=300):
    """Bound the entire child process tree, including browsers and Vite listeners."""
    with log.open('w') as output:
        process = subprocess.Popen(command, cwd=cwd, env=env, stdout=output,
                                   stderr=subprocess.STDOUT, stdin=subprocess.PIPE if stdin else subprocess.DEVNULL,
                                   start_new_session=True)
        try:
            process.communicate(input=stdin, timeout=timeout)
            return process.returncode
        except (subprocess.TimeoutExpired, KeyboardInterrupt) as failure:
            os.killpg(process.pid, signal.SIGTERM)
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGKILL)
                process.wait()
            # The parent may exit before a browser grandchild that ignored SIGTERM.
            try:
                os.killpg(process.pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
            if isinstance(failure, KeyboardInterrupt):
                raise
            return 124


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--agent', required=True, choices=['codex', 'claude'])
    parser.add_argument('--group', default='general', choices=['all', 'backend', 'general', *PROFILES])
    parser.add_argument('--list', action='store_true')
    args = parser.parse_args()
    groups = ['backend', 'general', *PROFILES] if args.group == 'all' else [args.group]
    if args.list:
        print(json.dumps({group: cases(group) if group != 'backend' else ['php-tests'] for group in groups}, indent=2))
        return 0
    out = ROOT / 'artifacts/regression' / args.agent / args.group
    out.mkdir(parents=True, exist_ok=True)
    lock = ROOT / '.local' / f'regression-{args.agent}.lock'
    lock.parent.mkdir(exist_ok=True)
    try:
        lock.mkdir()
    except FileExistsError:
        raise SystemExit('This Agent has another regression run; do not remove its lock')
    source = f'tasktracker-{args.agent}-test'
    offset = 100 if args.agent == 'claude' else 0
    frontend_port = 5198 if args.agent == 'claude' else 5199
    env = {**os.environ, 'E2E_AGENT': args.agent, 'E2E_ISOLATED': '1',
           'E2E_FRONTEND_PORT': str(frontend_port), 'TASKTRACKER_TEST_SOURCE_PROJECT': source,
           'TASKTRACKER_REGRESSION_IMAGE': f'tasktracker-{args.agent}-regression-backend',
           'TASKTRACKER_REGRESSION_RUNTIME_IMAGE': f'tasktracker-{args.agent}-regression-runtime',
           'REGRESSION_APP_KEY': 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
           'REGRESSION_SETUP_PORT': str(13201 + offset), 'REGRESSION_WS_PORT': str(13209 + offset)}
    for key in ['COMPOSE_FILE', 'COMPOSE_PROFILES', 'COMPOSE_PROJECT_NAME']:
        env.pop(key, None)
    compose = ['docker', 'compose', '--env-file', '/dev/null', '-p', f'tasktracker-{args.agent}-regression', '-f', 'docker-compose.regression.yml']
    results = []

    def check(name, command, *, cwd=ROOT, stdin=None, timeout=300):
        start = time.monotonic()
        code = run(command, env, out / f'{name}.log', cwd=cwd, stdin=stdin, timeout=timeout)
        results.append({'name': name, 'exitCode': code, 'seconds': round(time.monotonic() - start, 2), 'log': f'{name}.log'})
        (out / 'results.json').write_text(json.dumps(results, indent=2) + '\n')
        print(name, 'PASS' if code == 0 else f'FAIL ({code})', flush=True)
        return code == 0

    try:
        if args.group != 'backend':
            require_free_port(frontend_port)
        # Only the named Agent's known MySQL/account/schema may supply the shared server.
        preflight = "require 'vendor/autoload.php'; $app=require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); if(!$app->environment('testing') || getenv('TASKTRACKER_MYSQL_TEST')!=='1' || config('database.default')!=='mysql' || config('database.connections.mysql.host')!=='mysql' || config('database.connections.mysql.database')!=='tasktracker_test' || config('database.connections.mysql.username')!=='tasktracker_test') exit(1); echo 'Owned synthetic MySQL confirmed';"
        if not check('isolation', ['docker', 'exec', f'{source}-backend-1', 'php', '-r', preflight]):
            return 1
        if args.group != 'backend' and not check('build', ['npm', 'run', 'build'], cwd=ROOT / 'frontend'):
            return 1
        for group in groups:
            env.pop('E2E_SEED_PASSWORD', None)
            env['E2E_API_URL'] = f'http://127.0.0.1:{13198 if args.agent == "claude" else 13199}'
            if group == 'backend':
                ini = "mkdir -p /tmp/regression-php; printf 'memory_limit=512M\\n' > /tmp/regression-php/memory.ini"
                if check('backend-memory', ['docker', 'exec', f'{source}-backend-1', 'sh', '-c', ini]):
                    check('backend', ['docker', 'exec', '-e', 'PHP_INI_SCAN_DIR=:/tmp/regression-php', '-e', 'TASKTRACKER_AUTH_THROTTLE_SCALE=1', f'{source}-backend-1', 'php', 'artisan', 'test', '--do-not-cache-result'], timeout=600)
                continue
            if group not in PROFILES:
                for name in cases(group):
                    check(name, ['node', f'tools/{name}-check.js'], cwd=ROOT / 'frontend')
                continue
            profile = PROFILES[group]
            if group == 'seed':
                env['E2E_SEED_PASSWORD'] = 'isolated-regression-seed-password'
            env.update(E2E_REGRESSION_PROFILE=group, REGRESSION_API_PORT=str(profile['apiPort'] + offset))
            env['E2E_API_URL'] = f'http://127.0.0.1:{profile["apiPort"] + offset}'
            env['E2E_SETUP_API_URL'] = f'http://127.0.0.1:{13201 + offset}'
            env['PROD_BASE_URL'] = env['E2E_API_URL']
            if group == 'r2':
                path = ROOT / '.local/tt291-r2.env'
                valid = path.is_file() and not path.stat().st_mode & 0o077
                valid = valid and bool(re.search(r'^R2_BUCKET=[\'\"]?task-tracker-local[\'\"]?\s*$', path.read_text(), re.M))
                if not valid:
                    results.append({'name': 'r2', 'exitCode': None, 'status': 'blocked', 'reason': 'Private isolated task-tracker-local R2 configuration required'})
                    (out / 'results.json').write_text(json.dumps(results, indent=2) + '\n')
                    print('r2 BLOCKED: isolated configuration required', flush=True)
                    continue
                env['REGRESSION_R2_ENV_FILE'] = str(path)
            image = env['TASKTRACKER_REGRESSION_RUNTIME_IMAGE'] if group == 'runtime' else env['TASKTRACKER_REGRESSION_IMAGE']
            dockerfile = 'docker/production/Dockerfile' if group == 'runtime' else 'backend/Dockerfile'
            if not check(f'{group}-image', ['docker', 'build', '-f', dockerfile, '-t', image, '.'], timeout=600):
                continue
            database = profile['database']
            assert re.fullmatch(r'tasktracker_test_[a-z][a-z0-9]*', database)
            sql = f'CREATE DATABASE IF NOT EXISTS `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_bin; GRANT ALL PRIVILEGES ON `{database}`.* TO \'tasktracker_test\'@\'%\';'
            if not check(f'{group}-schema', ['docker', 'exec', '-i', f'{source}-mysql-1', 'sh', '-c', 'MYSQL_PWD="$(cat /run/tasktracker-root/password)" exec mysql -uroot'], stdin=sql.encode()):
                continue
            scoped = [*compose, '--profile', group]
            try:
                if group == 'runtime' and not check('runtime-migrate', [*scoped, 'run', '--rm', '--no-deps', 'runtime', 'php', 'artisan', 'migrate', '--force', '--no-interaction']):
                    continue
                if not check(f'{group}-start', [*scoped, 'up', '-d', '--wait'], timeout=120):
                    continue
                if group == 'seed' and not check('seed-data', [*scoped, 'exec', '-T', 'seed', 'php', 'artisan', 'tasktracker:seed-demo']):
                    continue
                for name in cases(group):
                    check(name, ['node', f'tools/{name}-check.js'], cwd=ROOT / 'frontend')
                if group == 'seed':
                    check('seed-roles', ['node', 'tools/browser-check.js'], cwd=ROOT / 'frontend')
                if group == 'r2':
                    check('r2-feature', [*scoped, 'exec', '-T', '-e', 'TASKTRACKER_AUTH_THROTTLE_SCALE=1', 'r2', 'php', 'artisan', 'test', '--filter=MySqlR2AttachmentsTest', '--do-not-cache-result'])
            finally:
                check(f'{group}-service-logs', [*scoped, 'logs', '--no-color', '--tail', '60'])
                check(f'{group}-cleanup', [*scoped, 'down'], timeout=90)
        return int(any(result.get('exitCode') != 0 for result in results))
    finally:
        lock.rmdir()


if __name__ == '__main__':
    raise SystemExit(main())
