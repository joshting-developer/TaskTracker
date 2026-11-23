#!/usr/bin/env python3
"""Read the one public-site and workspace origin pair used by releases and integrations."""
import argparse
import json
from pathlib import Path
from urllib.parse import urlsplit

PATH = Path(__file__).resolve().parent.parent / 'deploy' / 'origins.json'


def validate_origin(value):
    url = urlsplit(value) if isinstance(value, str) else None
    if not url or url.scheme != 'https' or not url.hostname or url.username or url.password or url.port or url.path or url.query or url.fragment:
        raise ValueError('Origins must be HTTPS origins without a path')
    return value


def load_origins(path=PATH):
    data = json.loads(Path(path).read_text())
    if set(data) != {'site', 'app'}:
        raise ValueError('Origins need exactly site and app')
    return {key: validate_origin(data[key]) for key in ('site', 'app')}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--field', choices=['site', 'app', 'target'], required=True)
    args = parser.parse_args()
    origins = load_origins()
    # 同一個origin維持合併建置；兩者不同時, Fly只放工作區, 介紹站另由Pages發布。
    print(('combined' if origins['site'] == origins['app'] else 'app') if args.field == 'target' else origins[args.field])


if __name__ == '__main__':
    main()
