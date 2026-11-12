#!/usr/bin/env python3
"""只讀Skill實體／入口／來源盤點；不讀取私有憑證或執行上游scripts。"""
import argparse
import collections
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess

ROOT = Path(__file__).resolve().parents[1]


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def inventory():
    modes = {}
    for line in subprocess.check_output(['git', 'ls-files', '-s', 'skills', '.agents/skills'], cwd=ROOT, text=True).splitlines():
        head, path = line.split('\t', 1)
        modes[path] = head.split()[0]
    skills = []
    shared = Path(os.environ.get('SIDE_PROJECT_SKILLS_ROOT', '/Users/josh/workspace/side-project/skills'))
    registry = json.loads((shared / 'registry.json').read_text())
    roots = [shared / item['path'] / 'SKILL.md' for item in registry['skills']]
    # rglob intentionally does not follow symlink directories: aliases are inspected separately.
    for path in roots:
        body = path.read_text()
        name = re.search(r'^name:\s*(.+)', body, re.M)
        real = path.resolve()
        aliases = []
        for entry in (ROOT / '.agents/skills').iterdir():
            if entry.is_symlink() and (entry / 'SKILL.md').resolve() == real:
                aliases.append({'path': str(entry.relative_to(ROOT)), 'target': os.readlink(entry), 'gitMode': modes.get(str(entry.relative_to(ROOT))), 'valid': entry.exists()})
        files = [p for p in path.parent.rglob('*') if p.is_file() and '__pycache__' not in p.parts]
        skills.append({'name': name[1].strip('"\'') if name else path.parent.name, 'source': os.path.relpath(path, ROOT), 'realpath': str(real), 'sha256': sha(path), 'bytes': path.stat().st_size, 'fileCount': len(files), 'entries': aliases, 'active': bool(aliases), 'sameNameGlobalExists': {host: (Path.home()/host/'skills'/path.parent.name).exists() for host in ['.codex', '.claude']}})
    broken = []
    for entry in (ROOT / '.agents/skills').iterdir():
        if entry.is_symlink() and not entry.exists():
            broken.append(str(entry.relative_to(ROOT)))
    checks = []; missing = []; mismatch = []

    def manifest_rows(node, base=None):
        if isinstance(node, dict):
            base = node.get('local_path', base)
            if 'sha256' in node and 'path' in node:
                relative = node['path']
                path = ROOT / relative if relative.startswith('skills/') else ROOT / (base or '') / relative
                row = os.path.relpath(path, ROOT)
                if not path.is_file(): missing.append(row)
                elif sha(path) != node['sha256']: mismatch.append(row)
                checks.append(row)
            for value in node.values(): manifest_rows(value, base)
        elif isinstance(node, list):
            for value in node: manifest_rows(value, base)
    for manifest in (ROOT / 'skills').glob('*sources.json'):
        manifest_rows(json.loads(manifest.read_text()))
    duplicates = collections.defaultdict(list)
    for path in (ROOT / 'skills').rglob('*'):
        if path.is_file() and not path.is_symlink() and '__pycache__' not in path.parts:
            duplicates[sha(path)].append(os.path.relpath(path, ROOT))
    repeated = [{'sha256': digest, 'paths': paths, 'licenseOnly': all('LICENSE' in Path(p).name for p in paths)} for digest, paths in duplicates.items() if len(paths) > 1]
    return {'phase': 'shared-canonical-library', 'skills': skills, 'brokenEntries': broken, 'manifest': {'checkedFiles': len(checks), 'missing': missing, 'mismatch': mismatch}, 'identicalFiles': repeated, 'hostEvidence': {'codexCliAvailable': bool(__import__('shutil').which('codex')), 'claudeCliAvailable': bool(__import__('shutil').which('claude')), 'freshSessionVerified': False}, 'visual': {'existingRunner': 'frontend/tools/browser-check.js', 'screenshots': True, 'approvedGoldenComparison': False, 'dependency': 'playwright (no @playwright/test)', 'ci': '.github/workflows/api-contract.yml'}}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--output', type=Path)
    args = parser.parse_args()
    result = inventory()
    content = json.dumps(result, ensure_ascii=False, indent=2) + '\n'
    if args.output: args.output.write_text(content)
    else: print(content, end='')


if __name__ == '__main__':
    main()
