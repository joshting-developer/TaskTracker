#!/usr/bin/env python3
"""Exercise public board APIs through the shared CLI, using isolated synthetic accounts only."""
import concurrent.futures
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import uuid

from public_client_acceptance import FixtureSession

ROOT = Path(__file__).resolve().parents[1]


def main():
    base = os.environ.get('E2E_API_URL')
    if os.environ.get('E2E_ISOLATED') != '1' or base != 'http://127.0.0.1:13199':
        raise RuntimeError('Requires the Codex isolated fixture backend on 13199')
    skill_root = Path(os.environ.get('SIDE_PROJECT_SKILLS_ROOT', '/Users/josh/workspace/side-project/skills'))
    cli = skill_root / '01-common/task-tracker-usage/scripts/tasktracker_api.py'
    sessions = []
    runner = """import importlib.util,json,pathlib,sys
spec=importlib.util.spec_from_file_location('tt_cli',sys.argv[1]);m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.PRIVATE=pathlib.Path(sys.argv[2]);sys.argv=sys.argv[2:];m.main()
"""
    try:
        owner = FixtureSession(base)
        sessions.append(owner)
        owner.register()
        owner.csrf = owner.call('/api/csrf-cookie')['csrfToken']
        owner.identity = owner.call('/api/auth/verify-email', {'code': 'local666'})
        project = owner.project('BOARDCLI')
        email = 'board-cli-' + str(uuid.uuid4()) + '@example.test'
        invite = owner.call('/api/rpc/inviteMember', {'email': email, 'requestId': str(uuid.uuid4())})
        other = FixtureSession(base)
        sessions.append(other)
        other.csrf = other.call('/api/csrf-cookie')['csrfToken']
        other.identity = other.call('/api/auth/register', {'name': 'Other synthetic agent', 'email': email, 'password': str(uuid.uuid4()), 'inviteToken': invite['token']})
        other.csrf = other.call('/api/csrf-cookie')['csrfToken']
        other.identity = other.call('/api/auth/verify-email', {'code': 'local666'})
        owner.call('/api/rpc/setProjectMember', {'projectId': project['id'], 'userId': other.identity['user']['id'], 'roleId': 'maintainer', 'expectedRevision': 0, 'requestId': str(uuid.uuid4())})
        with tempfile.TemporaryDirectory(prefix='tt332-private-') as folder:
            private = Path(folder)
            for agent, session in [('codex', owner), ('claude', other)]:
                token = session.token('write')
                config = {'agent': agent, 'baseUrl': base, 'userId': session.identity['user']['id'], 'workspaceId': owner.identity['workspace']['id'], 'token': token['plainTextToken'], 'expiresAt': token['token']['expiresAt']}
                path = private / (agent + '.json')
                path.write_text(json.dumps(config))
                path.chmod(0o600)

            def run(agent, *args, body=None, success=True):
                result = subprocess.run([sys.executable, '-c', runner, str(cli), str(private), '--agent', agent, '--project', project['id'], *args], input=json.dumps(body or {}), text=True, capture_output=True)
                if success and result.returncode:
                    # CLI errors are safe, but the runner's traceback could include invocation internals
                    raise RuntimeError('Shared board CLI command failed: ' + args[0])
                if not success:
                    assert result.returncode != 0 and 'HTTP 409' in result.stderr
                    return None
                return json.loads(result.stdout)

            board = run('codex', 'board-create', body={'name': 'API work', 'description': 'Public integrations'})
            board2 = run('codex', 'board-create', body={'name': 'Other board'})
            listed = run('codex', 'boards', '--per-page', '1')
            assert listed['total'] == 2 and len(listed['items']) == 1 and listed['defaultBoard']['isDefault']
            issue = run('codex', 'create', body={'title': 'Created and classified', 'boardIds': [board['id']]})
            assert run('codex', 'board-get', '--board', board['id'])['selectedIssueIds'] == [issue['id']]
            assert run('codex', 'board-issues', '--board', board['id'])['items'][0]['id'] == issue['id']
            other_issue = run('claude', 'create', body={'title': 'Other agent card'})
            # Both actual accounts start from the same revision; one must conflict rather than overwrite
            def add(agent, card):
                result = subprocess.run([sys.executable, '-c', runner, str(cli), str(private), '--agent', agent, '--project', project['id'], 'board-add', '--board', board2['id'], '--expected-revision', '1', '--issue-id', card], text=True, capture_output=True)
                return agent, card, result.returncode, result.stderr
            with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
                results = list(pool.map(lambda pair: add(*pair), [('codex', issue['id']), ('claude', other_issue['id'])]))
            assert sorted(row[2] for row in results) == [0, 1]
            loser = next(row for row in results if row[2])
            assert 'HTTP 409' in loser[3]
            fresh = run(loser[0], 'board-get', '--board', board2['id'])
            run(loser[0], 'board-add', '--board', board2['id'], '--expected-revision', str(fresh['revision']), '--issue-id', loser[1])
            current = run('codex', 'board-get', '--board', board2['id'])
            assert set(current['selectedIssueIds']) == {issue['id'], other_issue['id']}
            run('codex', 'board-remove', '--board', board2['id'], '--expected-revision', str(current['revision']), '--issue-id', issue['id'])
            assert run('codex', 'board-get', '--board', board2['id'])['selectedIssueIds'] == [other_issue['id']]
            run('codex', 'board-update', '--board', board['id'], body={'expectedRevision': 2, 'description': 'Updated purpose'})
            assert run('codex', 'board-get', '--board', board['id'])['description'] == 'Updated purpose'
            receipt = next(path for path in (private / 'outbox').iterdir() if json.loads(path.read_text())['operation'] == 'createPublicBoard')
            saved = json.loads(receipt.read_text())
            replayed = run('codex', 'retry', '--receipt', str(receipt))
            assert replayed['id'] in [board['id'], board2['id']] and saved['body']['requestId']
            print('PASS: shared CLI, two synthetic account PATs, paginated discovery, atomic classification, metadata, delta add/remove, real concurrent conflict/retry without lost membership, receipt replay')
    finally:
        for session in sessions:
            try:
                session.call('/api/auth/logout', {})
            except Exception:
                pass


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        # Never print fixture inputs, tokens, response bodies or subprocess tracebacks
        print('FAIL: ' + (str(error) if isinstance(error, RuntimeError) else type(error).__name__), file=sys.stderr)
        raise SystemExit(1)
