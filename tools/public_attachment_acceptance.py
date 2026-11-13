#!/usr/bin/env python3
"""Bootstrap isolated synthetic credentials, then run only the downloaded public example."""
import base64
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import urllib.request

from public_client_acceptance import FixtureSession


def main():
    base = os.environ.get('E2E_API_URL')
    if os.environ.get('E2E_ISOLATED') != '1' or base not in ['http://127.0.0.1:13089', 'http://127.0.0.1:13049']:
        raise SystemExit('Explicit isolated fixture service required')
    session = FixtureSession(base)
    try:
        session.register()
        project = session.project('IMGCLI')
        token = session.token('write')['plainTextToken']
        with urllib.request.urlopen(base + '/api/public/openapi.json', timeout=20) as response:
            assert not response.headers.get('Set-Cookie')
            spec = json.load(response)
        operations = {op['operationId'] for methods in spec['paths'].values() for op in methods.values()}
        assert {'getPublicAttachmentLimits', 'uploadPublicAttachment', 'getPublicAttachment', 'getPublicAttachmentContent', 'listPublicIssueAttachments', 'bindPublicAttachment'} <= operations
        with urllib.request.urlopen(base + '/api/public/docs/agents.md', timeout=20) as response:
            assert not response.headers.get('Set-Cookie')
            assert 'uploadPublicAttachment' in response.read().decode()
        # The child has no repo imports, Session client, CookieJar or private fixture access.
        with tempfile.TemporaryDirectory(prefix='tt-public-attachment-') as folder:
            client = Path(folder) / 'workflow.py'
            with urllib.request.urlopen(base + '/api/public/docs/examples.py', timeout=20) as response:
                client.write_bytes(response.read())
            image = Path(folder) / 'capture.png'
            image.write_bytes(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1cAAAAASUVORK5CYII='))
            result = subprocess.run([sys.executable, '-I', str(client)], cwd=folder,
                                    env={'PATH': os.environ['PATH'], 'CONFIRM_WRITE': '1', 'BASE_URL': base,
                                         'PROJECT_ID': project['id'], 'TASKTRACKER_TOKEN': token, 'IMAGE_FILE': str(image)},
                                    capture_output=True, text=True, timeout=90)
            if result.returncode:
                raise RuntimeError('Downloaded public example failed; no credentials or payload logged')
            data = json.loads(result.stdout)
            assert data['result'] == 'PASS' and data['attachmentVerified'] is True
            assert data['createdOnce'] is True and data['commentedOnce'] is True
            print(json.dumps({'result': 'PASS', 'client': 'anonymous downloaded Python example',
                              'uploadedOnce': True, 'descriptionBound': True, 'metadataRead': True,
                              'binaryChecksumVerified': True, 'cookieFree': True}))
    finally:
        if session.identity:
            session.call('/api/auth/logout', {})


if __name__ == '__main__':
    main()
