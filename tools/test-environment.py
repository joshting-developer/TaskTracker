#!/usr/bin/env python3
"""Manage a standalone synthetic test instance using the public root Compose file."""
import argparse
import os
import re
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]


def environment(*, port=13029, frontend_port=5175):
    result = {key: value for key, value in os.environ.items() if not key.startswith(('COMPOSE_', 'DB_', 'REDIS_', 'MAIL_', 'TASKTRACKER_'))}
    result.update(APP_ENV='testing', TASKTRACKER_MYSQL_TEST='1', TASKTRACKER_STANDALONE_TEST='1',
        DB_HOST='mysql', DB_DATABASE='tasktracker_test', DB_USERNAME='tasktracker_test',
        DB_PASSWORD_FILE='/run/tasktracker-app/password', DB_PASSWORD='',
        CACHE_STORE='file', QUEUE_CONNECTION='sync', BROADCAST_CONNECTION='null',
        TASKTRACKER_API_PORT=str(port), TASKTRACKER_MAIL_UI_PORT=str(port + 1000), APP_URL=f'http://127.0.0.1:{frontend_port}',
        ALLOWED_ORIGINS=f'http://127.0.0.1:{frontend_port},http://127.0.0.1:5175', TASKTRACKER_AUTH_THROTTLE_SCALE='50')
    for key in list(result):
        if key.startswith(('TASKTRACKER_TEST_', 'TASKTRACKER_IMPORT_')):
            result.pop(key)
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['up', 'down', 'status', 'config'])
    parser.add_argument('--name', default='tasktracker-tests')
    parser.add_argument('--port', type=int, default=13029)
    parser.add_argument('--frontend-port', type=int, default=5175)
    args = parser.parse_args()
    if not re.fullmatch(r'tasktracker-[a-z0-9-]+', args.name) or args.name == 'tasktracker-local-dev':
        parser.error('Use a dedicated tasktracker-* test instance name')
    if not 13000 <= args.port < 14000:
        parser.error('Use an isolated API port in 13000..13999')
    command = ['docker', 'compose', '--env-file', '/dev/null', '-p', args.name,
        '-f', str(ROOT / 'docker-compose.yml'), '--profile', 'standalone']
    operations = {'up': ['up', '-d', '--build', '--wait', 'backend', 'mysql', 'redis', 'mailpit'],
        'down': ['down'], 'status': ['ps'], 'config': ['config', '--quiet']}
    subprocess.run(command + operations[args.action], cwd=ROOT,
        env={**environment(port=args.port, frontend_port=args.frontend_port), 'TASKTRACKER_DATA_NETWORK': args.name + '-data'}, check=True)
    if args.action == 'up':
        print(f'E2E_ISOLATED=1 E2E_API_URL=http://127.0.0.1:{args.port} E2E_COMPOSE_PROJECT={args.name}')


if __name__ == '__main__':
    main()
