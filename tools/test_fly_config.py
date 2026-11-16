"""Private configuration handling without network or production credentials."""
import contextlib
import io
import os
from pathlib import Path
import stat
import tempfile
import unittest
from unittest.mock import patch

from fly_config import load_config, main, validate_config, write_config

CONFIG = '''app = "example-tasktracker"
primary_region = "nrt"
[env]
APP_ENV = "production"
APP_URL = "https://tasks.example.test"
DB_HOST = "private-db.example.test"
'''


class PrivateFlyConfigTest(unittest.TestCase):
    def test_secret_materializes_with_private_permissions_and_never_overwrites(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / 'fly.toml'
            write_config(path, CONFIG)
            self.assertEqual(stat.S_IMODE(path.stat().st_mode), 0o600)
            self.assertEqual(load_config(path)['env']['DB_HOST'], 'private-db.example.test')
            with self.assertRaises(FileExistsError):
                write_config(path, CONFIG)
            self.assertEqual(path.read_text(), CONFIG)

    def test_invalid_secret_does_not_create_file_or_echo_values(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder) / 'fly.toml'
            output = io.StringIO()
            with patch.dict(os.environ, {'FLY_CONFIG_TOML': 'private-invalid-sentinel'}), patch('sys.argv', ['fly_config.py', '--output', str(path)]), contextlib.redirect_stdout(output):
                with self.assertRaises(SystemExit) as result:
                    main()
            self.assertNotIn('private-invalid-sentinel', str(result.exception) + output.getvalue())
            self.assertFalse(path.exists())

    def test_refuses_unsafe_targets_before_deployment(self):
        valid = {'app': 'example-tasktracker', 'primary_region': 'nrt', 'env': {'APP_ENV': 'production', 'APP_URL': 'https://tasks.example.test'}}
        self.assertEqual(validate_config(valid), valid)
        for origin in ['http://tasks.example.test', 'https://user:password@tasks.example.test', 'https://tasks.example.test/path', 'https://tasks.example.test/?private=value']:
            with self.subTest(origin=origin), self.assertRaises(ValueError):
                validate_config({**valid, 'env': {**valid['env'], 'APP_URL': origin}})
        for app in ['wrong/app', 'private\napp', '']:
            with self.assertRaises(ValueError):
                validate_config({**valid, 'app': app})

    def test_public_example_is_independent_of_local_config(self):
        self.assertEqual(load_config('fly.toml.example')['app'], 'your-tasktracker-app')


if __name__ == '__main__':
    unittest.main()
