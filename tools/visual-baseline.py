#!/usr/bin/env python3
"""Capture synthetic candidates, verify approved baselines, and calibrate real screenshot failures."""
import argparse
import hashlib
import html
import json
import os
import platform
from pathlib import Path
import re
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
ARTIFACTS = ROOT / 'artifacts/visual'
CANDIDATE = ARTIFACTS / 'candidate'
PROFILE = sys.platform + '-' + platform.machine()
APPROVED = ARTIFACTS / ('approved-' + PROFILE)
APPROVAL = ROOT / 'docs/visual-baselines' / (PROFILE + '.json')
SOURCE_ROOTS = ['frontend/landing', 'frontend/src', 'backend/app', 'backend/routes', 'backend/config', 'frontend/tools/visual']


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def source_rows():
    paths = []
    for folder in SOURCE_ROOTS:
        paths += [p for p in (ROOT / folder).rglob('*') if p.is_file()]
    paths += [ROOT / name for name in ['backend/composer.json', 'backend/composer.lock', 'docker-compose.mysql-test.yml', 'frontend/index.html', 'frontend/package.json', 'frontend/package-lock.json', 'frontend/playwright.visual.config.js', 'backend/tools/visual-fixture-clock.php', 'tools/visual-baseline.py', 'tools/visual-check.sh', 'frontend/tools/navigation.js', 'frontend/tools/board-actions.js']]
    return [{'path': str(p.relative_to(ROOT)), 'sha256': digest(p)} for p in sorted(paths)]


def source_fingerprint():
    return hashlib.sha256(json.dumps(source_rows(), sort_keys=True).encode()).hexdigest()


def harness_fingerprint():
    paths = [ROOT / name for name in ['backend/composer.json', 'backend/composer.lock', 'docker-compose.mysql-test.yml', 'frontend/playwright.visual.config.js', 'tools/visual-baseline.py', 'tools/visual-check.sh', 'backend/tools/visual-fixture-clock.php', 'frontend/tools/navigation.js', 'frontend/tools/board-actions.js']]
    paths += [p for p in (ROOT / 'frontend/tools/visual').rglob('*') if p.is_file()]
    return hashlib.sha256(json.dumps([(str(p.relative_to(ROOT)), digest(p)) for p in sorted(paths)]).encode()).hexdigest()


def verify_bundle(bundle, manifest):
    files = manifest.get('files', [])
    if not files or len({row['path'] for row in files}) != len(files):
        raise ValueError('Missing or duplicate baseline images')
    for row in files:
        path = bundle / row['path']
        if not path.resolve().is_relative_to(bundle.resolve()) or not path.is_file() or digest(path) != row['sha256']:
            raise ValueError('Baseline image missing or changed: ' + row['path'])
    actual = {str(p.relative_to(bundle)) for p in (bundle / 'snapshots').rglob('*.png')}
    if actual != {row['path'] for row in files}:
        raise ValueError('Unrecorded baseline images')


def run_tests(bundle, label, update=False, inject=None, desktop=False):
    environment = os.environ.copy()
    environment['E2E_API_URL'] = 'http://127.0.0.1:13079'
    environment['VISUAL_SNAPSHOT_DIR'] = str(bundle / 'snapshots')
    environment['VISUAL_RESULTS_DIR'] = str(ARTIFACTS / label / 'results')
    environment['VISUAL_REPORT_FILE'] = str(ARTIFACTS / label / 'playwright.json')
    environment.pop('VISUAL_INJECT', None)
    if inject:
        environment['VISUAL_INJECT'] = inject
    command = [str(ROOT / 'frontend/node_modules/.bin/playwright'), 'test', '--config', 'playwright.visual.config.js', '--update-snapshots=' + ('all' if update else 'none')]
    if desktop:
        command += ['--project', 'desktop']
    output = ARTIFACTS / label
    if output.exists():
        shutil.rmtree(output)  # Only this invocation's fixed artifact label, never a baseline.
    output.mkdir(parents=True, exist_ok=True)
    with (output / 'command.log').open('w') as log:
        result = subprocess.run(command, cwd=ROOT / 'frontend', env=environment, stdout=log, stderr=subprocess.STDOUT, timeout=600)
    print(label, 'exit', result.returncode, flush=True)
    return result.returncode


def capture():
    if run_tests(CANDIDATE, 'capture', update=True):
        raise ValueError('Candidate behavior/capture failed; inspect artifacts/visual/capture/command.log')
    expected = set(re.findall(r"await shot\(page, '([^']+)'\)", (ROOT / 'frontend/tools/visual/flows.spec.js').read_text()))
    images = sorted((CANDIDATE / 'snapshots').glob('*/*.png'))
    actual = {str(p.relative_to(CANDIDATE)) for p in images}
    expected_paths = {f'snapshots/{viewport}/{name}.png' for viewport in ['desktop', 'tablet', 'mobile'] for name in expected}
    if actual != expected_paths:
        raise ValueError('Incomplete or stale candidate set')
    manifest = {'kind': 'unapproved-candidate', 'approved': False, 'harnessSha256': harness_fingerprint(), 'environment': json.loads((ARTIFACTS / 'environment.json').read_text()), 'sourceFingerprint': source_fingerprint(), 'files': [{'path': str(p.relative_to(CANDIDATE)), 'sha256': digest(p)} for p in images]}
    (CANDIDATE / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
    figures = ''.join(f'<figure><figcaption>{html.escape(row["path"])}</figcaption><a href="{html.escape(row["path"])}"><img loading="lazy" src="{html.escape(row["path"])}"></a></figure>' for row in manifest['files'])
    (CANDIDATE / 'index.html').write_text('<!doctype html><meta charset="utf-8"><title>TaskTracker visual candidate — unapproved</title><style>body{font:16px system-ui;margin:24px;background:#eee}main{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}figure{margin:0;background:white;padding:12px}img{width:100%}figcaption{margin-bottom:8px;overflow-wrap:anywhere}</style><h1>TaskTracker 候選畫面（尚未核准）</h1><p>'+str(len(images))+'張合成資料截圖。只記錄目前產品，不代表已重設計或已核准基準。點圖查看原尺寸。Manifest SHA256: '+digest(CANDIDATE / 'manifest.json')+'</p><main>'+figures+'</main>')
    print('Review:', CANDIDATE / 'index.html', '\nCandidate digest:', digest(CANDIDATE / 'manifest.json'))


def approval_preflight():
    approval = json.loads(APPROVAL.read_text())
    path = APPROVED / 'manifest.json'
    if approval.get('approved') is not True or not approval.get('reviewer') or not approval.get('note') or digest(path) != approval.get('manifestSha256'):
        raise ValueError('Missing or mismatched human approval')
    manifest = json.loads(path.read_text())
    verify_bundle(APPROVED, manifest)
    if approval.get('harnessSha256') != harness_fingerprint():
        raise ValueError('Visual harness changed since review; review and explicitly bind the changed harness before comparison')
    return manifest


def compare():
    manifest = approval_preflight()
    code = run_tests(APPROVED, 'comparison')
    verify_bundle(APPROVED, manifest)
    approval_preflight()
    environment_matches = json.loads((ARTIFACTS / 'environment.json').read_text()) == manifest['environment']
    passed = code == 0 and environment_matches
    result = {'status': 'passed' if passed else 'failed', 'approved': True, 'comparisonExitCode': code, 'environmentMatches': environment_matches, 'sourceFingerprint': source_fingerprint(), 'sourceFiles': source_rows(), 'sourceRoots': SOURCE_ROOTS, 'harnessSha256': harness_fingerprint(), 'approvalFile': str(APPROVAL.relative_to(ROOT)), 'manifestSha256': digest(APPROVED / 'manifest.json')}
    (ARTIFACTS / 'summary.json').write_text(json.dumps(result, indent=2) + '\n')
    if not passed:
        raise ValueError('Visual comparison or browser/platform baseline mismatch')


def approve(expected_digest, reviewer, note, artifact_run_id=None):
    if artifact_run_id and not artifact_run_id.isdecimal():
        raise ValueError('Artifact run ID must contain digits only')
    path = CANDIDATE / 'manifest.json'
    manifest = json.loads(path.read_text())
    if digest(path) != expected_digest or manifest['sourceFingerprint'] != source_fingerprint() or manifest.get('harnessSha256') != harness_fingerprint():
        raise ValueError('Candidate changed since review; capture/review again')
    verify_bundle(CANDIDATE, manifest)
    if APPROVED.exists():
        raise ValueError('Approved bundle already exists; archive it explicitly before replacing after review')
    shutil.copytree(CANDIDATE, APPROVED)
    APPROVAL.parent.mkdir(parents=True, exist_ok=True)
    APPROVAL.write_text(json.dumps({'approved': True, 'harnessSha256': harness_fingerprint(), 'profile': PROFILE, 'reviewer': reviewer, 'note': note, 'manifestSha256': expected_digest, 'environment': manifest['environment'], 'artifactRunId': artifact_run_id}, ensure_ascii=False, indent=2) + '\n')
    print('Approval recorded; run compare before claiming acceptance.')


def prove():
    manifest = json.loads((CANDIDATE / 'manifest.json').read_text())
    verify_bundle(CANDIDATE, manifest)
    rows = []
    for fault in [None, 'spacing', 'overflow', 'hide-action', None]:
        label = 'calibration-' + (fault or ('restored' if rows else 'before'))
        code = run_tests(CANDIDATE, label, inject=fault, desktop=True)
        expected_failure = fault is not None
        passed = (code != 0) if expected_failure else code == 0
        # A failed assertion elsewhere is not evidence of a visual regression detector.
        diffs = list((ARTIFACTS / label / 'results').rglob('05-issues-diff.png'))
        if expected_failure:
            passed = passed and bool(diffs)
        rows.append({'fault': fault, 'exitCode': code, 'expectedFailure': expected_failure, 'diffs': [str(p.relative_to(ARTIFACTS)) for p in diffs], 'passed': passed})
    verify_bundle(CANDIDATE, manifest)
    (ARTIFACTS / 'regression-proof.json').write_text(json.dumps({'kind': 'calibration-only-not-human-approval', 'passed': all(row['passed'] for row in rows), 'manifestSha256': digest(CANDIDATE / 'manifest.json'), 'sourceFingerprint': source_fingerprint(), 'harnessSha256': harness_fingerprint(), 'environment': manifest['environment'], 'results': rows}, indent=2) + '\n')
    if not all(row['passed'] for row in rows):
        raise ValueError('Regression calibration failed')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode', choices=['candidate', 'compare', 'prove', 'approve', 'preflight'])
    parser.add_argument('--digest')
    parser.add_argument('--reviewer')
    parser.add_argument('--note')
    parser.add_argument('--artifact-run-id', help='Reviewed CI candidate run, when applicable')
    args = parser.parse_args()
    ARTIFACTS.mkdir(parents=True, exist_ok=True)
    (ARTIFACTS / 'summary.json').write_text(json.dumps({'status': 'running', 'approved': False, 'mode': args.mode}) + '\n')
    if args.mode in ['candidate', 'prove']:
        (ARTIFACTS / 'regression-proof.json').write_text(json.dumps({'passed': False, 'status': 'pending', 'mode': args.mode}) + '\n')
    try:
        if args.mode == 'candidate': capture()
        elif args.mode == 'compare': compare()
        elif args.mode == 'prove': prove()
        elif args.mode == 'preflight': approval_preflight()
        else:
            if not all([args.digest, args.reviewer, args.note]):
                parser.error('approve requires explicit reviewed digest, reviewer and review note; never run automatically')
            approve(args.digest, args.reviewer, args.note, args.artifact_run_id)
    except (OSError, ValueError, KeyError, subprocess.TimeoutExpired) as error:
        (ARTIFACTS / 'summary.json').write_text(json.dumps({'status': 'not-verified', 'approved': False, 'error': str(error)}, ensure_ascii=False, indent=2) + '\n')
        print(str(error), file=sys.stderr)
        raise SystemExit(1)


if __name__ == '__main__':
    main()
