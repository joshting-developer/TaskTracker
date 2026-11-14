#!/usr/bin/env python3
"""隔離HTTP fixture建立PAT，下載公開範例並實跑，最後撤銷Token。"""
import http.cookiejar
import json
import os
from pathlib import Path
import subprocess
import tempfile
import urllib.request
import uuid


def main():
    base = os.environ.get('E2E_API_URL')
    if base not in {'http://127.0.0.1:13029', 'http://127.0.0.1:13049', 'http://127.0.0.1:13198', 'http://127.0.0.1:13199'}:
        raise SystemExit('Only isolated test API ports are allowed')
    opener = urllib.request.build_opener(
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    csrf = ''

    def call(path, data=None):
        nonlocal csrf
        headers = {'Accept': 'application/json'}
        if data is not None:
            headers.update({'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf})
        request = urllib.request.Request(
            base + path, headers=headers,
            data=None if data is None else json.dumps(data).encode())
        with opener.open(request, timeout=20) as response:
            csrf = response.headers.get('X-CSRF-TOKEN') or csrf
            body = json.load(response)
        return body.get('data', body)

    csrf = call('/api/csrf-cookie')['csrfToken']
    registration = call('/api/auth/register', {
        'email': str(uuid.uuid4()) + '@example.test', 'name': 'Contract fixture',
        'password': str(uuid.uuid4()), 'workspaceName': 'Isolated contracts'})
    if registration.get('verificationRequired'):
        if os.environ.get('E2E_ISOLATED') != '1':
            raise RuntimeError('Explicit isolated fixture activation required')
        call('/api/auth/verify-email', {'code': 'local666'})
    token = None
    try:
        project = call('/api/rpc/createProject', {
            'name': 'HTTP Project', 'code': 'HTTP', 'requestId': str(uuid.uuid4())})
        issue = call('/api/rpc/createIssue', {
            'projectId': project['id'], 'title': 'HTTP issue',
            'requestId': str(uuid.uuid4())})
        token = call('/api/v1/personal-tokens', {
            'name': 'Downloaded examples', 'abilities': ['write'],
            'requestId': str(uuid.uuid4())})
        env = {**os.environ, 'BASE_URL': base,
               'TASKTRACKER_TOKEN': token['plainTextToken'],
               'PROJECT_ID': project['id'], 'ISSUE_IDENTIFIER': issue['identifier'],
               'CONFIRM_WRITE': '1'}
        # 只傳公開URL、PAT與測試輸入；下載的客戶端不匯入fixture或應用原始碼。
        with tempfile.TemporaryDirectory(prefix='tasktracker-contract-examples-') as folder:
            for extension, interpreter in [('py', 'python3'), ('sh', 'sh')]:
                with urllib.request.urlopen(base + '/api/public/docs/examples.' + extension) as response:
                    source = response.read()
                path = Path(folder) / ('workflow.' + extension)
                path.write_bytes(source)
                path.chmod(0o600)
                subprocess.run([interpreter, str(path)], cwd=folder, env=env, check=True)
    finally:
        try:
            if token:
                call('/api/v1/personal-tokens/' + token['token']['id'] + '/revoke', {})
        finally:
            call('/api/auth/logout', {})


if __name__ == '__main__':
    main()
