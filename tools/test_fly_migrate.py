import importlib.util
import subprocess
import unittest
from pathlib import Path
from types import SimpleNamespace

spec = importlib.util.spec_from_file_location('migration', Path(__file__).with_name('fly-migrate.py'))
m = importlib.util.module_from_spec(spec)
spec.loader.exec_module(m)
CONFIG = {'app': 'example-tasktracker', 'primary_region': 'nrt', 'env': {'APP_ENV': 'production'}, 'files': [{'guest_path': '/run/ca.pem', 'secret_name': 'MYSQL_CA_BASE64'}]}
IMAGE = 'registry.fly.io/example-tasktracker:v0.4.0'


class FakeMachines:
    def __init__(self, uncertain=False, state='started'):
        self.calls, self.machine, self.uncertain, self.state = [], None, uncertain, state

    def request(self, method, path='', body=None):
        self.calls.append((method, path, body))
        if method == 'POST':
            self.machine = {**body, 'id': 'owned-machine'}
            if self.uncertain:
                raise TimeoutError('response lost')
            return self.machine
        if method == 'DELETE':
            assert path == '/owned-machine?force=true'
            self.machine = None
            return None
        return [self.machine] if not path else {'state': self.state}


class MigrationTest(unittest.TestCase):
    def test_success_keeps_credentials_only_on_stdin_and_destroys_machine(self):
        client = FakeMachines()
        def execute(command, **kwargs):
            self.assertNotIn('sensitive-password', str(command))
            self.assertIn('sensitive-password', kwargs['input'])
            self.assertEqual(kwargs['timeout'], 240)
            return SimpleNamespace(returncode=0)
        m.run(CONFIG, IMAGE, {'username': 'migrator', 'password': 'sensitive-password'}, client, execute)
        self.assertIsNone(client.machine)
        payload = client.calls[0][2]
        self.assertEqual(payload['config']['services'], [])
        self.assertEqual(payload['config']['restart'], {'policy': 'no'})
        self.assertNotIn('sensitive-password', str(payload))
        self.assertEqual(payload['config']['files'], CONFIG['files'])

    def test_failure_and_timeout_always_remove_owned_machine(self):
        for failure in [SimpleNamespace(returncode=1), subprocess.TimeoutExpired('ssh', 240)]:
            with self.subTest(failure=type(failure).__name__):
                client = FakeMachines()
                def execute(*args, **kwargs):
                    if isinstance(failure, Exception):
                        raise failure
                    return failure
                with self.assertRaises((RuntimeError, subprocess.TimeoutExpired)):
                    m.run(CONFIG, IMAGE, {'username': 'm', 'password': 'p'}, client, execute)
                self.assertIsNone(client.machine)

    def test_uncertain_create_recovers_by_unique_name_without_retry(self):
        client = FakeMachines(uncertain=True)
        with self.assertRaises(TimeoutError):
            m.run(CONFIG, IMAGE, {'username': 'm', 'password': 'p'}, client)
        self.assertIsNone(client.machine)
        self.assertEqual(sum(method == 'POST' for method, *_ in client.calls), 1)

    def test_rejects_wrong_registry_or_unversioned_image(self):
        for image in ['registry.fly.io/example-tasktracker:latest', 'registry.fly.io/other:v0.4.0', 'example.com/tasktracker:v0.4.0']:
            with self.assertRaises(ValueError):
                m.migration_config(CONFIG, image)

    def test_deployment_requires_successful_migration_before_http(self):
        source = Path('tools/fly-tagged-release.sh').read_text()
        self.assertLess(source.index('python3 tools/fly-migrate.py'), source.index('flyctl deploy'))
        self.assertNotIn('release_command', Path('fly.toml.example').read_text())
        self.assertNotIn('MIGRATION_DB_PASSWORD', str(CONFIG))


if __name__ == '__main__':
    unittest.main()
