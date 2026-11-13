#!/usr/bin/env python3
"""Verify CLI operation mapping and revision acknowledgement without credentials or network."""
import argparse
import importlib.util
import json
from pathlib import Path
import sys
import tempfile
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]


def module(path, name):
    spec = importlib.util.spec_from_file_location(name, path)
    value = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(value)
    return value


pat = module(ROOT / '.agents/skills/task-tracker-usage/scripts/tasktracker_api.py', 'pat')
legacy = module(ROOT / 'tools/tasktracker.py', 'session_cli')


class Fake:
    def __init__(self, *args):
        self.calls = []

    def call(self, operation, **kwargs):
        self.calls.append((operation, kwargs))
        if operation == 'getPublicTodo':
            return {'id': 'synthetic', 'revision': 7, 'capabilities': {'comment': True, 'forward': True, 'complete': True}}
        return {'items': [], 'page': 1, 'lastPage': 1}

    def write(self, operation, ids, body):
        self.calls.append((operation, body))
        return {'id': 'synthetic', 'revision': 8}


with tempfile.TemporaryDirectory(prefix='tt325-cli-') as directory:
    body = Path(directory) / 'body.json'
    for command, operation, payload in [
        ('todo-comment', 'commentPublicTodo', {'content': 'Test reply', 'expectedRevision': 7}),
        ('todo-forward', 'forwardPublicTodo', {'assigneeId': 'recipient', 'reply': 'Test handoff', 'expectedRevision': 7}),
    ]:
        body.write_text(json.dumps(payload))
        fake = Fake()
        with patch.object(pat, 'Client', return_value=fake), patch.object(sys, 'argv', ['cli', '--agent', 'codex', command, '--todo', 'synthetic', '--data-file', str(body)]):
            pat.main()
        assert fake.calls[-1] == (operation, payload), fake.calls
        body.write_text(json.dumps({key: val for key, val in payload.items() if key != 'expectedRevision'}))
        with patch.object(pat, 'Client', return_value=Fake()), patch.object(sys, 'argv', ['cli', '--agent', 'codex', command, '--todo', 'synthetic', '--data-file', str(body)]):
            try:
                pat.main()
                raise AssertionError('Missing revision must fail')
            except RuntimeError as error:
                assert 'expectedRevision' in str(error)
    for command, operation in [('todo-comments', 'getPublicTodoComments'), ('todo-handoffs', 'getPublicTodoHandoffs')]:
        fake = Fake()
        with patch.object(pat, 'Client', return_value=fake), patch.object(sys, 'argv', ['cli', '--agent', 'codex', command, '--todo', 'synthetic', '--page', '2', '--per-page', '20']):
            pat.main()
        assert fake.calls[-1] == (operation, {'ids': {'todo': 'synthetic'}, 'query': {'page': 2, 'perPage': 20}})
    reply = Path(directory) / 'reply.txt'
    reply.write_text('Completed and tested')
    fake = Fake()
    with patch.object(pat, 'Client', return_value=fake), patch.object(sys, 'argv', ['cli', '--agent', 'codex', 'todo-complete', '--todo', 'synthetic', '--reply-file', str(reply)]):
        pat.main()
    assert fake.calls[-1] == ('completePublicTodo', {'expectedRevision': 7, 'reply': 'Completed and tested'})
    class SessionFake:
        def __init__(self): self.sent = None
        def call(self, path): return {'id': 'synthetic', 'revision': 7, 'capabilities': {'comment': True, 'forward': True, 'complete': True}}
        def rpc(self, operation, **payload): self.sent = (operation, payload)
    for command, operation in [('todo-comment', 'commentTodo'), ('todo-forward', 'forwardTodo'), ('todo-complete', 'completeTodo')]:
        fake = SessionFake()
        args = argparse.Namespace(agent='codex', command=command, identifier='synthetic', expected_revision=7, body_file=reply, assignee_id='recipient')
        with patch.object(legacy, 'ROOT', Path(directory)):
            legacy.handle_todo(fake, args)
        assert fake.sent[0] == operation
        assert fake.sent[1]['expectedRevision'] == 7
        assert fake.sent[1]['content' if command == 'todo-comment' else 'reply'] == reply.read_text()
        if command == 'todo-forward': assert fake.sent[1]['assigneeId'] == 'recipient'
    for path in (Path(directory) / '.local/tasktracker-outbox').glob('*.json'):
        assert path.stat().st_mode & 0o077 == 0
print('PASS CLI explicit revision, reply payload, forward target, bounded conversation pages; no credentials/network used')
