#!/usr/bin/env python3
"""獨立測試fixture控制器；客戶端只收到公開規格／文件、PAT與測試輸入。"""
import http.cookiejar
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]


class FixtureSession:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf = ''
        self.identity = None

    def call(self, path, data=None):
        headers = {'Accept': 'application/json'}
        if data is not None:
            headers.update({'Content-Type': 'application/json', 'X-CSRF-TOKEN': self.csrf})
        request = urllib.request.Request(self.base + path, headers=headers,
                                         data=None if data is None else json.dumps(data).encode())
        with self.opener.open(request, timeout=20) as response:
            self.csrf = response.headers.get('X-CSRF-TOKEN') or self.csrf
            body = json.load(response)
        return body.get('data', body)

    def register(self):
        self.csrf = self.call('/api/csrf-cookie')['csrfToken']
        self.identity = self.call('/api/auth/register', {
            'email': 'contract-client-' + str(uuid.uuid4()) + '@example.test',
            'name': 'Independent client actor', 'password': str(uuid.uuid4()),
            'workspaceName': 'Client acceptance'})

    def project(self, code):
        return self.call('/api/rpc/createProject', {
            'name': 'Client project', 'code': code, 'requestId': str(uuid.uuid4())})

    def token(self, ability):
        return self.call('/api/v1/personal-tokens', {
            'name': 'Client acceptance', 'abilities': [ability], 'requestId': str(uuid.uuid4())})


def main():
    base = os.environ.get('E2E_API_URL')
    if base != 'http://127.0.0.1:13049':
        raise SystemExit('Only tasktracker-contract-ci on 13049 is allowed')
    sessions = []
    try:
        first = FixtureSession(base)
        first.register()
        sessions.append(first)
        project = first.project('CLIENT')
        hidden = first.project('HIDDEN')
        tokens = [first.token(ability) for ability in ['write', 'read', 'write', 'write']]
        second = FixtureSession(base)
        second.register()
        sessions.append(second)
        other = second.project('OTHER')

        def control(action, **extra):
            payload = {'userId': first.identity['user']['id'], 'projectId': project['id'],
                       'action': action, **extra}
            subprocess.run([
                'docker', 'compose', '-p', 'tasktracker-contract-ci', '-f',
                str(ROOT / 'docker-compose.mysql-test.yml'), 'exec', '-T', 'backend',
                'php', '/app/tools/public-client-fixture.php'],
                cwd=ROOT, input=json.dumps(payload), text=True, check=True, capture_output=True)

        control('maintainer')
        config = {'baseUrl': base, 'projectId': project['id'],
                  'token': tokens[0]['plainTextToken'], 'readToken': tokens[1]['plainTextToken'],
                  'hiddenProjectId': hidden['id'], 'otherWorkspaceProjectId': other['id'],
                  'actorName': first.identity['user']['name']}
        # 空目錄中只放單一通用客戶端；-I移除PYTHONPATH／cwd的匯入機會。
        with tempfile.TemporaryDirectory(prefix='tasktracker-independent-client-') as folder:
            client = Path(folder) / 'client.py'
            shutil.copyfile(ROOT / 'tools/clients/public_contract_client.py', client)
            client.chmod(0o600)

            def run(phase, status=200, **extra):
                result = subprocess.run(
                    [sys.executable, '-I', str(client)], cwd=folder,
                    input=json.dumps({**config, 'phase': phase, 'status': status, **extra}),
                    text=True, check=False, capture_output=True,
                    env={'PATH': os.environ['PATH']})
                if result.returncode:
                    raise RuntimeError('Independent client failed: ' + result.stderr)
                data = json.loads(result.stdout)
                assert data['result'] == 'PASS'
                print('PASS: independent public client ' + phase + ' / ' + str(status))
                return data

            state = run('workflow')['state']
            # 同一PAT在資格變更前後發新HTTP請求，不能只驗預先失效的Token。
            control('reader')
            run('reader', state=state)
            control('remove-project')
            run('read', 404)
            control('maintainer')
            run('read')
            control('disable-workspace')
            run('identity', 401)
            control('maintainer')
            run('identity')
            run('identity', token=tokens[2]['plainTextToken'])
            control('expire-token', tokenId=tokens[2]['token']['id'])
            run('identity', 401, token=tokens[2]['plainTextToken'])
            run('identity', token=tokens[3]['plainTextToken'])
            first.call('/api/v1/personal-tokens/' + tokens[3]['token']['id'] + '/revoke', {})
            run('identity', 401, token=tokens[3]['plainTextToken'])
    finally:
        # 資格及Token的fixture資料隨獨立Compose tmpfs移除，Session另明確登出。
        for session in sessions:
            session.call('/api/auth/logout', {})


if __name__ == '__main__':
    main()
