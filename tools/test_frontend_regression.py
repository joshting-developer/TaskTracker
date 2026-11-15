import importlib.util
import os
from pathlib import Path
import socket
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('regression', ROOT / 'tools/frontend-regression.py')
runner = importlib.util.module_from_spec(spec)
spec.loader.exec_module(runner)


class RegressionRunnerTest(unittest.TestCase):
    def test_every_check_has_exactly_one_group(self):
        actual = [name for group in ['general', *runner.PROFILES] for name in runner.cases(group)]
        expected = [p.stem.removesuffix('-check') for p in (ROOT / 'frontend/tools').glob('*-check.js')]
        self.assertCountEqual(expected, actual)
        self.assertEqual(len(actual), len(set(actual)))

    def test_busy_port_is_rejected_without_stopping_owner(self):
        with socket.socket() as server:
            server.bind(('127.0.0.1', 0))
            port = server.getsockname()[1]
            with self.assertRaises(SystemExit):
                runner.require_free_port(port)
            self.assertEqual(port, server.getsockname()[1])

    def test_timeout_stops_the_child_process_group(self):
        with tempfile.TemporaryDirectory() as directory:
            log = Path(directory) / 'timeout.log'
            result = runner.run([sys.executable, '-c', 'import time; time.sleep(30)'], os.environ.copy(), log, timeout=0.1)
            self.assertEqual(124, result)

    def test_node_fixture_rejects_business_url_before_docker_access(self):
        script = "import { ownedTopology } from './frontend/tools/fixtures/owned-topology.js'; ownedTopology('pagination');"
        env = {**os.environ, 'E2E_AGENT': 'codex', 'E2E_ISOLATED': '1', 'E2E_REGRESSION_PROFILE': 'pagination', 'E2E_API_URL': 'https://task-tracker.josh-ting.com'}
        result = subprocess.run(['node', '--input-type=module', '-e', script], cwd=ROOT, env=env, capture_output=True)
        self.assertNotEqual(0, result.returncode)
        self.assertIn(b'AssertionError', result.stderr)

    def test_default_fixture_rejects_the_other_agents_backend(self):
        script = "import { ownedDefaultTopology } from './frontend/tools/fixtures/owned-topology.js'; ownedDefaultTopology();"
        env = {**os.environ, 'E2E_AGENT': 'claude', 'E2E_ISOLATED': '1', 'E2E_API_URL': 'http://127.0.0.1:13199'}
        result = subprocess.run(['node', '--input-type=module', '-e', script], cwd=ROOT, env=env, capture_output=True)
        self.assertNotEqual(0, result.returncode)
        self.assertIn(b'AssertionError', result.stderr)


if __name__ == '__main__':
    unittest.main()
