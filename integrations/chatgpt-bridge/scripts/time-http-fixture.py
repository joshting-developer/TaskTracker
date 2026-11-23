"""Run time/board adapter checks against an explicitly owned synthetic backend."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import uuid
ROOT = Path(__file__).resolve().parents[3]
base = os.environ.get('E2E_API_URL')
agent = os.environ.get('E2E_AGENT')
if os.environ.get('E2E_ISOLATED') != '1' or {'codex':'http://127.0.0.1:13199','claude':'http://127.0.0.1:13198'}.get(agent) != base:
    raise SystemExit('An explicit owned isolated backend is required')
spec = importlib.util.spec_from_file_location('fixture', ROOT / 'tools/public_client_acceptance.py')
fixture = importlib.util.module_from_spec(spec); spec.loader.exec_module(fixture)
owner=fixture.FixtureSession(base);owner.register();project=owner.project('BTIME');other=owner.project('OTHER')
member=fixture.FixtureSession(base);member.register()
invite=owner.call('/api/rpc/inviteMember',{'email':member.identity['user']['email'],'requestId':str(uuid.uuid4())})
member.identity=member.call('/api/auth/acceptWorkspaceInvite',{'inviteToken':invite['token'],'requestId':str(uuid.uuid4())})
owner.call('/api/rpc/setProjectMember',{'projectId':project['id'],'userId':member.identity['user']['id'],'roleId':'developer','expectedRevision':0,'requestId':str(uuid.uuid4())})
outsider=fixture.FixtureSession(base);outsider.register();outside=outsider.project('OUT')
config={'baseUrl':base,'project':project['id'],'otherProject':other['id'],'outsideProject':outside['id'],'workspaceId':owner.identity['workspace']['id'],'token':owner.token('write')['plainTextToken'],'readToken':owner.token('read')['plainTextToken'],'memberToken':member.token('write')['plainTextToken']}
r=subprocess.run(['node',str(Path(__file__).with_name('time-http-check.mjs'))],input=json.dumps(config),text=True,capture_output=True,cwd=ROOT)
output=r.stdout+r.stderr
for k in ['token','readToken','memberToken']:output=output.replace(config[k],'[REDACTED]')
print(output)
if r.returncode:raise SystemExit(r.returncode)
i=owner.call(f"/api/v1/projects/{project['id']}/issues/{json.loads(r.stdout.splitlines()[-1])['issue']}")
assert i['spentMinutes']==0
print('PASS: web issue reads the same voided time total')
