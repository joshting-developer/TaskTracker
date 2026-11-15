"""Exercise retry boundaries without network, credentials or production writes."""
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

SCRIPT = Path(__file__).with_name('fly-tagged-release.sh').resolve()


class ReleaseRetryTest(unittest.TestCase):
    def run_release(self, existing, image='sha256:verified'):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            (root / 'tools').mkdir()
            (root / 'tools/check-release-archive.sh').write_text('exit 0\n')
            commands = {
                'curl': 'exit 0',
                'python3': 'cat >/dev/null; exit 0',
                'flyctl': 'exit 0',
                'docker': '''case "$1 $2" in
"manifest inspect")
  if test "$EXISTING" = absent; then echo 'manifest unknown' >&2; exit 1; fi
  if test "$EXISTING" = denied; then echo 'unauthorized' >&2; exit 1; fi;;
"inspect --format") printf '%s\\n' "$EXISTING_IMAGE";;
esac''',
            }
            for name, body in commands.items():
                path = root / name
                path.write_text('#!/bin/sh\nprintf "%s %s\\n" "${0##*/}" "$*" >> "$CALL_LOG"\n' + body + '\n')
                path.chmod(0o700)
            env = {**os.environ, 'PATH': f'{root}:' + os.environ['PATH'],
                   'FLY_API_TOKEN': 'synthetic', 'MIGRATION_DB_USERNAME': 'synthetic',
                   'MIGRATION_DB_PASSWORD': 'synthetic', 'RELEASE_ARCHIVE': 'synthetic',
                   'RELEASE_ARCHIVE_SHA': 'synthetic', 'RELEASE_IMAGE_ID': 'sha256:verified',
                   'RELEASE_SOURCE_SHA': 'synthetic', 'RELEASE_TAG': 'v0.4.0',
                   'EXISTING': existing, 'EXISTING_IMAGE': image,
                   'CALL_LOG': str(root / 'calls'), 'GITHUB_STEP_SUMMARY': str(root / 'summary')}
            result = subprocess.run(['sh', str(SCRIPT)], cwd=root, env=env, capture_output=True, text=True)
            return result, (root / 'calls').read_text()

    def test_identical_published_image_is_reused_without_push(self):
        result, calls = self.run_release('present')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('docker pull registry.fly.io/tasktracker-josh:v0.4.0', calls)
        self.assertNotIn('docker push', calls)
        self.assertIn('flyctl deploy', calls)

    def test_different_image_stops_before_migration_or_deployment(self):
        result, calls = self.run_release('present', 'sha256:different')
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('tools/fly-migrate.py', calls)
        self.assertNotIn('flyctl deploy', calls)
        self.assertNotIn('docker push', calls)

    def test_missing_version_is_published(self):
        result, calls = self.run_release('absent')
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertIn('docker push registry.fly.io/tasktracker-josh:v0.4.0', calls)

    def test_registry_denial_is_not_treated_as_missing_version(self):
        result, calls = self.run_release('denied')
        self.assertNotEqual(result.returncode, 0)
        self.assertNotIn('docker push', calls)
        self.assertNotIn('flyctl deploy', calls)


if __name__ == '__main__':
    unittest.main()
