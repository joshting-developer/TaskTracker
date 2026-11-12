#!/usr/bin/env python3
"""公開API寫入範例，僅標準HTTP與環境PAT；必須明確CONFIRM_WRITE=1。"""
import json
import os
import urllib.error
import urllib.request
import urllib.parse
import uuid


def main():
    if os.environ.get('CONFIRM_WRITE') != '1':
        raise SystemExit('此範例會建立卡片、更新與留言，請明確設定CONFIRM_WRITE=1')
    base = os.environ['BASE_URL'].rstrip('/')
    token = os.environ['TASKTRACKER_TOKEN']
    project = os.environ['PROJECT_ID']
    prefix = '/api/public/v1/projects/' + project + '/issues'
    def call(method, path, data=None):
        headers = {'Accept': 'application/json', 'Authorization': 'Bearer ' + token}
        if data is not None:
            headers['Content-Type'] = 'application/json'
        request = urllib.request.Request(base + path, method=method, headers=headers, data=None if data is None else json.dumps(data).encode())
        try:
            response = urllib.request.urlopen(request, timeout=20)
        except urllib.error.HTTPError as error:
            # 不輸出URL／payload／header或可能的秘密。
            raise RuntimeError('API拒絕操作，HTTP ' + str(error.code)) from None
        with response:
            assert not response.headers.get('Set-Cookie')
            return json.load(response)['data']
    call('GET', '/api/public/v1/me')
    call('GET', '/api/public/v1/projects?perPage=20')
    metadata = call('GET', '/api/public/v1/projects/' + project)
    states = call('GET', '/api/public/v1/projects/' + project + '/statuses')['items']
    assert any(state['id'] == 'closed' for state in states)
    request = {'title': 'HTTP API example ' + str(uuid.uuid4())[:8], 'requestId': str(uuid.uuid4())}
    created = call('POST', prefix, request)
    assert created == call('POST', prefix, request), '相同key須回原結果'
    path = prefix + '/' + created['id']
    page = call('GET', prefix + '?' + urllib.parse.urlencode({'identifier': created['identifier']}))
    assert page['total'] == 1 and page['items'][0]['id'] == created['id']
    detail = call('GET', path)
    patch = {'state': 'closed', 'expectedRevision': detail['revision'], 'requestId': str(uuid.uuid4())}
    updated = call('PATCH', path, patch)
    comment = {'content': 'Verified using the documented public HTTP API.', 'expectedRevision': updated['revision'], 'requestId': str(uuid.uuid4())}
    commented = call('POST', path + '/comments', comment)
    assert commented == call('POST', path + '/comments', comment)
    final = call('GET', path)
    comments = call('GET', path + '/comments')
    assert final['state'] == 'closed' and final['revision'] == commented['revision']
    assert comments['total'] == 1
    print(json.dumps({'result': 'PASS', 'createdOnce': True, 'commentedOnce': True, 'finalRevision': final['revision'], 'closed': True}))


if __name__ == '__main__':
    main()
