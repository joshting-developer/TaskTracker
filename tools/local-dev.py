#!/usr/bin/env python3
"""Start only the dedicated synthetic local development stack."""
import argparse
import json
import os
from pathlib import Path
import secrets
import subprocess

ROOT = Path(__file__).resolve().parents[1]
PRIVATE = ROOT / '.local'
COMPOSE = ROOT / 'docker-compose.local-dev.yml'
PROJECT = 'tasktracker-local-dev'


def initialize():
    PRIVATE.mkdir(exist_ok=True, mode=0o700)
    env = PRIVATE / 'local-dev.env'
    credentials = PRIVATE / 'local-dev-credentials.json'
    if not env.exists():
        password = secrets.token_urlsafe(20)
        fd = os.open(env, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        with os.fdopen(fd, 'w') as target:
            target.write('TASKTRACKER_SEED_PASSWORD=' + password + '\n')
        credentials.write_text(json.dumps({'baseUrl': 'http://127.0.0.1:5174', 'password': password,
            'accounts': [name + '@example.test' for name in ['owner', 'manager', 'maintainer', 'developer']]}, indent=2) + '\n')
        credentials.chmod(0o600)
    elif not credentials.is_file():
        raise SystemExit('Missing local credentials; preserve local-dev.env and restore its credentials record')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['up', 'down', 'status', 'seed', 'logs', 'config'])
    args = parser.parse_args()
    initialize()
    # Never inherit COMPOSE_FILE, project names, external service variables or the root .env.
    environment = {key: value for key, value in os.environ.items() if not key.startswith('COMPOSE_')}
    # The dedicated Compose contains no environment interpolation; Docker keeps its credential-helper context.
    command = ['docker', 'compose', '--env-file', '/dev/null', '-p', PROJECT, '-f', str(COMPOSE)]
    operations = {'up': ['up', '-d', '--build', '--wait'], 'down': ['down'],
        'status': ['ps'], 'logs': ['logs', '--tail=60', 'backend', 'frontend', 'queue'],
        'seed': ['exec', '-T', 'backend', 'php', 'artisan', 'tasktracker:seed-demo'], 'config': ['config', '--quiet']}
    subprocess.run(command + operations[args.action], cwd=ROOT, env=environment, check=True)
    if args.action in ['up', 'seed']:
        print('Local development: http://127.0.0.1:5174')
        print('Synthetic account credentials: .local/local-dev-credentials.json')
        print('Seed explicitly with: python3 tools/local-dev.py seed')


if __name__ == '__main__':
    main()
