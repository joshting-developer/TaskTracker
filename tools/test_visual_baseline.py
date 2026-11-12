#!/usr/bin/env python3
"""Approval/hash safety checks using only temporary synthetic files."""
import hashlib
import importlib.util
import json
import subprocess
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('visual_baseline', Path(__file__).with_name('visual-baseline.py'))
visual = importlib.util.module_from_spec(spec)
spec.loader.exec_module(visual)


class VisualBaselineTest(unittest.TestCase):
    def test_missing_tampered_and_escaping_files_are_rejected(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            folder = root / 'snapshots/desktop'
            folder.mkdir(parents=True)
            image = folder / 'example.png'
            image.write_bytes(b'synthetic verifier fixture, not a product image')
            manifest = {'files': [{'path': 'snapshots/desktop/example.png', 'sha256': visual.digest(image)}]}
            visual.verify_bundle(root, manifest)
            image.write_bytes(b'changed')
            with self.assertRaises(ValueError): visual.verify_bundle(root, manifest)
            manifest['files'][0]['path'] = '../outside.png'
            with self.assertRaises(ValueError): visual.verify_bundle(root, manifest)
            with self.assertRaises(ValueError): visual.verify_bundle(root, {'files': []})

    def test_runner_discards_old_diffs_before_a_failed_process(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            old = root / 'calibration-spacing/results'
            old.mkdir(parents=True)
            (old / '05-issues-diff.png').write_bytes(b'old')
            with patch.object(visual, 'ARTIFACTS', root), patch.object(visual.subprocess, 'run') as process:
                process.return_value.returncode = 1
                self.assertEqual(visual.run_tests(root / 'candidate', 'calibration-spacing', inject='spacing'), 1)
                self.assertFalse(old.exists())

    def test_atomic_lock_refuses_another_runner_without_cleanup(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            (root / 'tools').mkdir()
            script = root / 'tools/visual-check.sh'
            script.write_text(Path(__file__).with_name('visual-check.sh').read_text())
            lock = root / '.local/visual-run.lock'
            lock.mkdir(parents=True)
            sentinel = lock / 'other-owner'
            sentinel.write_text('keep')
            result = subprocess.run(['sh', str(script), 'candidate'], capture_output=True, text=True)
            self.assertEqual(result.returncode, 2)
            self.assertIn('busy or has a stale lock', result.stderr)
            self.assertEqual(sentinel.read_text(), 'keep')

    def test_approval_is_explicit_and_does_not_refresh_existing_baseline(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            candidate = root / 'candidate'
            approved = root / 'approved'
            candidate.mkdir()
            folder = candidate / 'snapshots/desktop'
            folder.mkdir(parents=True)
            image = folder / 'example.png'
            image.write_bytes(b'synthetic fixture')
            manifest = {'harnessSha256': 'harness', 'sourceFingerprint': 'source', 'environment': {'platform': 'test-only'}, 'files': [{'path': 'snapshots/desktop/example.png', 'sha256': visual.digest(image)}]}
            (candidate / 'manifest.json').write_text(json.dumps(manifest))
            approval = root / 'approval.json'
            with patch.multiple(visual, CANDIDATE=candidate, APPROVED=approved, APPROVAL=approval), patch.object(visual, 'source_fingerprint', return_value='source'), patch.object(visual, 'harness_fingerprint', return_value='harness'):
                with self.assertRaises(FileNotFoundError): visual.approval_preflight()
                with self.assertRaises(ValueError): visual.approve('wrong-digest', 'synthetic-reviewer', 'unit test only')
                self.assertFalse(approved.exists())
                visual.approve(visual.digest(candidate / 'manifest.json'), 'synthetic-reviewer', 'unit test only')
                visual.approval_preflight()
                with patch.object(visual, 'harness_fingerprint', return_value='changed-policy'):
                    with self.assertRaises(ValueError): visual.approval_preflight()
                with self.assertRaises(ValueError): visual.approve(visual.digest(candidate / 'manifest.json'), 'synthetic-reviewer', 'unit test only')
                (approved / 'snapshots/desktop/example.png').write_bytes(b'tampered')
                with self.assertRaises(ValueError): visual.approval_preflight()


if __name__ == '__main__':
    unittest.main()
