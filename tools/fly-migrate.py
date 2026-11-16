#!/usr/bin/env python3
"""Run migrations in a disposable, service-free Fly Machine; stdin holds DB credentials."""
import argparse
import json
import os
import re
import subprocess
import time
import urllib.error
import urllib.request
import uuid
from fly_config import load_config


class Machines:
    def __init__(self, token, app):
        self.token, self.base = token, f'https://api.machines.dev/v1/apps/{app}/machines'

    def request(self, method, path='', body=None):
        request = urllib.request.Request(self.base + path, data=json.dumps(body).encode() if body is not None else None,
                                         method=method, headers={'Authorization': 'Bearer ' + self.token, 'Content-Type': 'application/json'})
        with urllib.request.urlopen(request, timeout=30) as response:
            content = response.read()
            return json.loads(content) if content else None


def migration_config(config, image):
    app = config['app']
    if not re.fullmatch(r'registry\.fly\.io/' + re.escape(app) + r':v\d+\.\d+\.\d+(?:-[A-Za-z0-9.-]+)?', image):
        raise ValueError('Migration requires the immutable version image in this app registry')
    return {'image': image, 'env': config.get('env', {}), 'files': config.get('files', []),
            'init': {'exec': ['/bin/sleep', '600']}, 'services': [], 'restart': {'policy': 'no'},
            'guest': {'cpu_kind': 'shared', 'cpus': 1, 'memory_mb': 512},
            'auto_destroy': True, 'metadata': {'tasktracker_role': 'migration'}}


def run(config, image, credentials, client, execute=subprocess.run, sleep=time.sleep, monotonic=time.monotonic):
    machine = None
    name = 'tt-migration-' + uuid.uuid4().hex[:16]
    try:
        # Never retry a create. An uncertain response is recovered by the unique name in finally.
        machine = client.request('POST', body={'name': name, 'region': config['primary_region'], 'config': migration_config(config, image)})
        deadline = monotonic() + 120
        while True:
            state = client.request('GET', '/' + machine['id'])['state']
            if state == 'started':
                break
            if state in ['stopped', 'destroyed'] or monotonic() >= deadline:
                raise RuntimeError('Migration Machine did not start')
            sleep(2)
        result = execute(['flyctl', 'ssh', 'console', '--app', config['app'], '--machine', machine['id'],
                          '-C', 'php /app/deploy/migrate.php'], input=json.dumps(credentials), text=True, capture_output=True, timeout=240)
        # Do not relay raw process logs, credentials or framework exceptions into CI.
        if result.returncode:
            raise RuntimeError('Migration process failed; HTTP deployment was not started')
        print('PASS: version image migration completed')
    finally:
        if machine is None:
            rows = client.request('GET')
            matching = [row for row in rows if row.get('name') == name and row.get('config', {}).get('metadata', {}).get('tasktracker_role') == 'migration']
            if len(matching) > 1:
                raise RuntimeError('Ambiguous migration Machine recovery')
            machine = matching[0] if matching else None
        if machine:
            client.request('DELETE', '/' + machine['id'] + '?force=true')
            print('Migration Machine removed')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--image', required=True)
    parser.add_argument('--config', default=os.environ.get('FLY_CONFIG_PATH', 'fly.toml'))
    args = parser.parse_args()
    token = os.environ.get('FLY_API_TOKEN', '')
    credentials = {'username': os.environ.get('MIGRATION_DB_USERNAME', ''), 'password': os.environ.get('MIGRATION_DB_PASSWORD', '')}
    if not token or not all(credentials.values()):
        raise SystemExit('Missing deployment or migration credential')
    try:
        config = load_config(args.config)
        run(config, args.image, credentials, Machines(token, config['app']))
    except Exception:
        raise SystemExit('Migration failed or cleanup incomplete; do not deploy HTTP, inspect the protected job context') from None


if __name__ == '__main__':
    main()
