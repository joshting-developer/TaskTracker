#!/usr/bin/env python3
import tempfile
import unittest
from pathlib import Path
from importlib.util import module_from_spec, spec_from_file_location

spec = spec_from_file_location('release_migrations', Path(__file__).with_name('check-release-migrations.py'))
module = module_from_spec(spec)
spec.loader.exec_module(module)


class ReleaseMigrationGuardTest(unittest.TestCase):
    def test_pending_schema_is_detected_and_applied_schema_is_allowed(self):
        with tempfile.TemporaryDirectory() as folder:
            directory = Path(folder)
            for name in ['0001_create_users', '0002_add_receipts']:
                (directory / (name + '.php')).write_text('<?php')
            self.assertEqual(module.pending_migrations(directory, ['0001_create_users']), ['0002_add_receipts'])
            self.assertEqual(module.pending_migrations(directory, ['0001_create_users', '0002_add_receipts']), [])

    def test_missing_directory_and_invalid_snapshot_fail_closed(self):
        with tempfile.TemporaryDirectory() as folder:
            with self.assertRaises(ValueError):
                module.pending_migrations(Path(folder), [])
            with self.assertRaises(ValueError):
                module.pending_migrations(Path(folder), {'migration': 'untrusted'})


if __name__ == '__main__':
    unittest.main()
