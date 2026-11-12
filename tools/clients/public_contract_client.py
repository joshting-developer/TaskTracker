#!/usr/bin/env python3
"""工具中立、無應用依賴的公開文件／PAT客戶端驗收；輸入走stdin，不印憑證。"""
import json
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid


def main():
    config = json.load(sys.stdin)
    base = config['baseUrl'].rstrip('/')

    def fetch(path, token=None, method='GET', body=None, status=200):
        headers = {'Accept': 'application/json'}
        if token:
            headers['Authorization'] = 'Bearer ' + token
        if body is not None:
            headers['Content-Type'] = 'application/json'
        request = urllib.request.Request(base + path, method=method, headers=headers,
                                         data=None if body is None else json.dumps(body).encode())
        try:
            response = urllib.request.urlopen(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            assert response.status == status, f'Unexpected HTTP status: {response.status}, expected {status}'
            assert 'Set-Cookie' not in response.headers, 'Bearer client must not require Session'
            return response.read()

    # 所有業務路徑與methods取自公開OpenAPI，不補內部RPC或未公開欄位。
    spec = json.loads(fetch('/api/public/openapi.json'))
    for path in ['/api/public/docs/authentication.md', '/api/public/docs/agents.md', '/api/public/contract.md']:
        assert fetch(path).startswith(b'#'), 'Public guide is unavailable'
    operations = {op['operationId']: (method.upper(), path)
                  for path, methods in spec['paths'].items() for method, op in methods.items()}
    token = config['token']
    project = config['projectId']

    def call(operation, body=None, query=None, status=200, credential=None, **ids):
        method, template = operations[operation]
        assert template.startswith('/api/public/v1/'), 'Client only uses public Bearer operations'
        path = template.format(project=project, **ids)
        if query:
            path += '?' + urllib.parse.urlencode(query)
        result = json.loads(fetch(path, credential or token, method, body, status))
        if status != 200:
            assert not result['ok']
            return result['error']
        assert result['ok']
        return result['data']

    phase = config.get('phase', 'workflow')
    if phase == 'identity':
        call('getTokenIdentity', status=config['status'])
    elif phase == 'read':
        call('getPublicProject', status=config['status'])
    elif phase == 'reader':
        call('getPublicProject')
        # 已成功請求的重播亦必須重驗當下權限，不可繞過撤權。
        error = call('createPublicIssue', body=config['state']['createBody'], status=403)
        assert error['code'] == 'FORBIDDEN'
    else:
        identity = call('getTokenIdentity')
        assert 'write' in identity['abilities']
        visible = call('listPublicProjects', query={'perPage': 1})
        assert visible['perPage'] == 1
        assert visible['total'] == 1 and visible['items'][0]['id'] == project
        states = call('listPublicProjectStatuses')['items']
        assert {'open', 'closed'} <= {row['id'] for row in states}
        body = {'title': 'Independent HTTP task', 'type': 'task', 'requestId': str(uuid.uuid4())}
        created = call('createPublicIssue', body=body)
        # 模擬回應遺失：重送原請求識別，不建立另一張卡片。
        assert created == call('createPublicIssue', body=body)
        issue = created['id']
        query = {'identifier': created['identifier'], 'perPage': 1}
        found = call('listPublicIssues', query=query)
        assert found['total'] == 1 and found['items'][0]['id'] == issue
        latest = call('getPublicIssue', issue=issue)
        original_revision = latest['revision']
        changed = call('updatePublicIssue', issue=issue, body={
            'description': 'Updated from public documentation', 'state': 'closed',
            'expectedRevision': original_revision, 'requestId': str(uuid.uuid4())})
        stale_draft = {'title': 'Stale draft must survive', 'expectedRevision': original_revision,
                       'requestId': str(uuid.uuid4())}
        error = call('updatePublicIssue', issue=issue, body=stale_draft, status=409)
        assert error['code'] == 'REVISION_CONFLICT'
        reread = call('getPublicIssue', issue=issue)
        assert reread['revision'] == changed['revision'] and reread['title'] == body['title']
        assert stale_draft['expectedRevision'] == original_revision
        # 讀回之後不自動偷換草稿版本／強制覆蓋。
        comment = {'content': 'Independent client comment', 'expectedRevision': reread['revision'],
                   'requestId': str(uuid.uuid4())}
        written = call('createPublicIssueComment', issue=issue, body=comment)
        assert written == call('createPublicIssueComment', issue=issue, body=comment)
        comments = call('listPublicIssueComments', issue=issue)
        assert comments['total'] == 1 and comments['items'][0]['content'] == comment['content']
        assert comments['items'][0]['author'] == config['actorName']
        error = call('createPublicIssue', body={**body, 'title': 'Different payload'}, status=409)
        assert error['code'] == 'REQUEST_CONFLICT'
        assert call('createPublicIssue', body=body, status=403,
                    credential=config['readToken'])['code'] == 'FORBIDDEN'
        for inaccessible in [config['hiddenProjectId'], config['otherWorkspaceProjectId']]:
            # 同一operation，目標ID僅是測試輸入；不推斷不可見資源內容。
            original = project
            project = inaccessible
            error = call('getPublicProject', status=404)
            assert error['code'] == 'NOT_FOUND'
            project = original
        final = call('getPublicIssue', issue=issue)
        assert final['state'] == 'closed' and final['revision'] == 3
        print(json.dumps({'phase': phase, 'result': 'PASS', 'state': {'createBody': body},
                          'replaySafe': True, 'draftPreserved': True}))
        return
    print(json.dumps({'phase': phase, 'result': 'PASS'}))


if __name__ == '__main__':
    main()
