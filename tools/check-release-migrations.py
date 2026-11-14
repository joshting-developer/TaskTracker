#!/usr/bin/env python3
"""Reject unattended schema changes before any production image deployment."""
import argparse
import json
from pathlib import Path


def pending_migrations(directory, applied):
    if not isinstance(applied, list) or any(not isinstance(name, str) for name in applied):
        raise ValueError('Applied migrations must be a list of names')
    expected = {path.stem for path in directory.glob('*.php')}
    if not expected:
        raise ValueError('Release migration directory is empty')
    return sorted(expected - set(applied))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--applied-file', type=Path, required=True)
    parser.add_argument('--migration-directory', type=Path, default=Path('backend/database/migrations'))
    args = parser.parse_args()
    pending = pending_migrations(args.migration_directory, json.loads(args.applied_file.read_text()))
    if pending:
        raise SystemExit('Schema changes need a separately verified private backup and migration plan: ' + ', '.join(pending))
    print('PASS: release migrations are already applied; migration command is a no-op')


if __name__ == '__main__':
    main()
