"""Verify transport corruption and source/version substitutions fail before deployment."""
import hashlib
import os
from pathlib import Path
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]


class ArchiveChecks(unittest.TestCase):
    def test_verified_source_and_corrupted_or_substituted_images(self):
        with tempfile.TemporaryDirectory(prefix='tt-release-archive-') as name:
            folder = Path(name)
            archive = folder / 'image.tar'
            archive.write_bytes(b'synthetic isolated archive')
            docker = folder / 'docker'
            docker.write_text('''#!/bin/sh
if [ "$1" = load ]; then exit 0; fi
case "$3" in
  *'.Id'*) echo sha256:known-image ;;
  *revision*) echo known-source ;;
  *version*) echo v0.3.0 ;;
  *) exit 2 ;;
esac
''')
            docker.chmod(0o700)
            env = dict(os.environ, PATH=str(folder)+os.pathsep+os.environ['PATH'],
                       RELEASE_ARCHIVE=str(archive), RELEASE_ARCHIVE_SHA=hashlib.sha256(archive.read_bytes()).hexdigest(),
                       RELEASE_IMAGE_ID='sha256:known-image', RELEASE_SOURCE_SHA='known-source', RELEASE_TAG='v0.3.0')
            cases = [{}, {'RELEASE_ARCHIVE_SHA': '0'*64}, {'RELEASE_IMAGE_ID': 'sha256:substituted'},
                     {'RELEASE_SOURCE_SHA': 'unverified-source'}, {'RELEASE_TAG': 'v9.9.9'}]
            for overrides in cases:
                with self.subTest(overrides=overrides):
                    result = subprocess.run(['sh', str(ROOT/'tools/check-release-archive.sh')], env={**env, **overrides},
                                            capture_output=True, text=True)
                    self.assertEqual(result.returncode == 0, not overrides)


if __name__ == '__main__':
    unittest.main()
