"""領卡版本保護與獨立帳號選擇；不連業務資料庫。"""
import contextlib
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch

import tasktracker


class FakeClient:
    def __init__(self, config=None):
        self.config = config or {'userId': 'codex-id'}
        self.calls = []

    def rpc(self, operation, **payload):
        self.calls.append((operation, payload))
        return {'id': 'issue-id', 'revision': payload['expectedRevision'] + 1,
                'assignee': self.config['userId'], 'state': payload.get('state', 'open')}


class ClaimTests(unittest.TestCase):
    def issue(self, **fields):
        return {'id': 'issue-id', 'revision': 5, 'assignee': '', 'state': 'open', **fields}

    def test_assign_precedes_state_with_returned_revision_and_distinct_request_ids(self):
        client = FakeClient()
        tasktracker.claim_issue(client, self.issue())
        first, second = [payload for _, payload in client.calls]
        self.assertEqual(first['assignee'], 'codex-id')
        self.assertNotIn('state', first)
        self.assertEqual(second['state'], 'inprogress')
        self.assertEqual([first['expectedRevision'], second['expectedRevision']], [5, 6])
        self.assertNotEqual(first['requestId'], second['requestId'])

    def test_never_steals_or_reopens(self):
        for fields in [{'assignee': 'claude-id'}, {'state': 'closed'}, {'state': 'deferred'}]:
            client = FakeClient()
            with self.assertRaises(RuntimeError):
                tasktracker.claim_issue(client, self.issue(**fields))
            self.assertEqual(client.calls, [])

    def test_conflict_stops_before_state_change(self):
        client = FakeClient()
        with patch.object(client, 'rpc', side_effect=tasktracker.ApiError('REVISION_CONFLICT', 'changed')) as rpc:
            with self.assertRaises(tasktracker.ApiError):
                tasktracker.claim_issue(client, self.issue())
            self.assertEqual(rpc.call_count, 1)

    def test_resume_after_assignment_and_repeat_inprogress(self):
        client = FakeClient()
        tasktracker.claim_issue(client, self.issue(assignee='codex-id'))
        self.assertEqual(len(client.calls), 1)
        self.assertNotIn('assignee', client.calls[0][1])
        tasktracker.claim_issue(client, self.issue(assignee='codex-id', state='inprogress'))
        self.assertEqual(len(client.calls), 1)

    def test_cli_selects_each_private_file_and_logs_out(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            (root / '.local').mkdir()
            for name in ['codex', 'claude']:
                (root / f'.local/tasktracker-{name}.json').write_text(json.dumps({'displayName': name, 'userId': name}))
                with patch.object(tasktracker, 'ROOT', root), patch.object(tasktracker, 'Client') as factory, patch.object(tasktracker, 'MANIFEST') as manifest, patch('sys.argv', ['tasktracker.py', '--agent', name, 'list']), contextlib.redirect_stdout(io.StringIO()):
                    manifest.read_text.return_value = '{}'
                    factory.return_value.list_issues.return_value = []
                    tasktracker.main()
                    self.assertEqual(factory.call_args.args[0]['userId'], name)
                    factory.return_value.login.assert_called_once()
                    factory.return_value.logout.assert_called_once()


if __name__ == '__main__':
    unittest.main()
