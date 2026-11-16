"""Run the Bridge HTTP checks with synthetic credentials on the dedicated 13049 API."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import uuid

ROOT = Path(__file__).resolve().parents[3]
if os.environ.get('E2E_ISOLATED') != '1':
    raise SystemExit('E2E_ISOLATED=1 is required')
spec = importlib.util.spec_from_file_location('fixture', ROOT / 'tools/public_client_acceptance.py')
fixture = importlib.util.module_from_spec(spec)
spec.loader.exec_module(fixture)
session = fixture.FixtureSession('http://127.0.0.1:13049')
session.register()
project = session.project('BRIDGE')
write = session.token('write')['plainTextToken']
read = session.token('read')['plainTextToken']
recipient = fixture.FixtureSession(session.base)
recipient.register()
invite = session.call('/api/rpc/inviteMember', {'email': recipient.identity['user']['email'], 'requestId': str(uuid.uuid4())})
recipient.identity = recipient.call('/api/auth/acceptWorkspaceInvite', {'inviteToken': invite['token'], 'requestId': str(uuid.uuid4())})
recipient_token = recipient.token('write')['plainTextToken']
config = {'baseUrl': session.base, 'projectId': project['id'],
          'workspaceId': session.identity['workspace']['id'], 'token': write, 'readToken': read,
          'recipientToken': recipient_token, 'recipientId': recipient.identity['user']['id']}
result = subprocess.run(['node', str(Path(__file__).with_name('http-check.mjs'))],
                        input=json.dumps(config), text=True, capture_output=True, cwd=ROOT)
print((result.stdout + result.stderr).replace(write, '[REDACTED]').replace(read, '[REDACTED]').replace(recipient_token, '[REDACTED]'))
raise SystemExit(result.returncode)
