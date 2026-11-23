#!/usr/bin/env python3
"""Load a private Fly config, or materialize one from a deployment-only secret."""
import argparse
import os
from pathlib import Path
import re
import tomllib
from urllib.parse import urlsplit

from site_origins import load_origins


def validate_config(config):
    app = config.get('app', '')
    region = config.get('primary_region', '')
    environment = config.get('env', {})
    origin = environment.get('APP_URL', '')
    url = urlsplit(origin)
    if not re.fullmatch(r'[a-z0-9][a-z0-9-]{0,62}', app) or not re.fullmatch(r'[a-z]{3}', region):
        raise ValueError('Invalid deployment target')
    if url.scheme != 'https' or not url.hostname or url.username or url.password or url.path or url.query or url.fragment:
        raise ValueError('Production APP_URL must be an HTTPS origin')
    if environment.get('APP_ENV') != 'production':
        raise ValueError('Production environment required')
    return config


def check_origins(config, origins):
    # TT-379：網域只在deploy/origins.json改一次；私有設定不一致就不部署。
    environment = config.get('env', {})
    allowed = [value.strip() for value in environment.get('ALLOWED_ORIGINS', origins['app']).split(',')]
    site = config.get('build', {}).get('args', {}).get('VITE_SITE_ORIGIN', origins['site'])
    if environment.get('APP_URL') != origins['app'] or origins['app'] not in allowed or site != origins['site']:
        raise ValueError('Private config origins differ from deploy/origins.json')
    return config


def load_config(path):
    return validate_config(tomllib.loads(Path(path).read_text()))


def write_config(path, content):
    validate_config(tomllib.loads(content))
    # Do not overwrite another invocation's config; never print its content.
    fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(fd, 'w') as target:
        target.write(content)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', default=os.environ.get('FLY_CONFIG_PATH', 'fly.toml'))
    parser.add_argument('--output', type=Path)
    parser.add_argument('--field', choices=['app', 'origin'])
    args = parser.parse_args()
    try:
        if args.output:
            content = os.environ.get('FLY_CONFIG_TOML', '')
            if not content:
                raise ValueError('Missing private config secret')
            check_origins(tomllib.loads(content), load_origins())
            write_config(args.output, content)
            print('Private deployment config ready')
        else:
            config = check_origins(load_config(args.config), load_origins())
            if args.field:
                print(config['app'] if args.field == 'app' else config['env']['APP_URL'])
    except (ValueError, OSError, TypeError):
        raise SystemExit('Private Fly config missing or invalid; values withheld') from None


if __name__ == '__main__':
    main()
