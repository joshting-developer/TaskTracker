#!/usr/bin/env python3
"""公開API寫入範例，僅標準HTTP與環境PAT；必須明確CONFIRM_WRITE=1。"""
import json
import hashlib
from pathlib import Path
import os
import urllib.error
import urllib.request
import urllib.parse
import uuid


def main():
    if os.environ.get('CONFIRM_WRITE') != '1':
        raise SystemExit('此範例會建立卡片、更新、留言與小待辦，請明確設定CONFIRM_WRITE=1')
    base = os.environ['BASE_URL'].rstrip('/')
    token = os.environ['TASKTRACKER_TOKEN']
    project = os.environ['PROJECT_ID']
    class NoRedirect(urllib.request.HTTPRedirectHandler):
        def redirect_request(self, request, fp, code, message, headers, new_url):
            return None
    opener = urllib.request.build_opener(NoRedirect())
    prefix = '/api/public/v1/projects/' + project + '/issues'
    def call(method, path, data=None):
        headers = {'Accept': 'application/json', 'Authorization': 'Bearer ' + token}
        if data is not None:
            headers['Content-Type'] = 'application/json'
        request = urllib.request.Request(base + path, method=method, headers=headers, data=None if data is None else json.dumps(data).encode())
        try:
            response = opener.open(request, timeout=20)
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
    image = None
    image_file = os.environ.get('IMAGE_FILE')
    if image_file:
        # Opt in with a local image; never fetch a remote image URL.
        limits = call('GET', '/api/public/v1/projects/' + project + '/attachments/limits')
        image_path = Path(image_file)
        if not 0 < image_path.stat().st_size <= limits['maxBytes']:
            raise RuntimeError('Image exceeds the deployment limit')
        suffix = image_path.suffix.lower()
        mime = {'.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp'}.get(suffix)
        if mime not in limits['mimeTypes']:
            raise RuntimeError('Unsupported local image extension')
        with image_path.open('rb') as source:
            image_bytes = source.read(limits['maxBytes'] + 1)
        if not 0 < len(image_bytes) <= limits['maxBytes']:
            raise RuntimeError('Image changed or exceeds the deployment limit')
        boundary = 'tt-' + uuid.uuid4().hex
        upload_id = str(uuid.uuid4())
        body = ('--' + boundary + '\r\nContent-Disposition: form-data; name="requestId"\r\n\r\n' + upload_id + '\r\n--' + boundary + '\r\nContent-Disposition: form-data; name="file"; filename="capture' + suffix + '"\r\nContent-Type: ' + mime + '\r\n\r\n').encode() + image_bytes + ('\r\n--' + boundary + '--\r\n').encode()
        upload_request = urllib.request.Request(base + '/api/public/v1/projects/' + project + '/attachments', method='POST', headers={'Authorization': 'Bearer ' + token, 'Accept': 'application/json', 'Content-Type': 'multipart/form-data; boundary=' + boundary}, data=body)
        def upload():
            try:
                with opener.open(upload_request, timeout=30) as response:
                    assert not response.headers.get('Set-Cookie')
                    return json.load(response)['data']
            except urllib.error.HTTPError as error:
                raise RuntimeError('Upload rejected, HTTP ' + str(error.code)) from None
        image = upload()
        assert image == upload(), 'Same file and requestId must replay the original upload'
        request['description'] = '![capture](' + image['reference'] + ')'
    created = call('POST', prefix, request)
    assert created == call('POST', prefix, request), '相同key須回原結果'
    path = prefix + '/' + created['id']
    page = call('GET', prefix + '?' + urllib.parse.urlencode({'identifier': created['identifier']}))
    assert page['total'] == 1 and page['items'][0]['id'] == created['id']
    detail = call('GET', path)
    if image:
        attachments = call('GET', path + '/attachments')
        assert attachments['perPage'] == 20 and attachments['total'] == 1
        metadata_path = '/api/public/v1/projects/' + project + '/attachments/' + image['id']
        assert call('GET', metadata_path)['status'] == 'active'
        binary_request = urllib.request.Request(base + metadata_path + '/content?download=true', headers={'Authorization': 'Bearer ' + token})
        with opener.open(binary_request, timeout=20) as response:
            assert response.headers['Content-Type'].split(';')[0] == image['mime']
            assert 'no-store' in response.headers['Cache-Control']
            assert not response.headers.get('Set-Cookie') and not response.headers.get('Location')
            assert hashlib.sha256(response.read(limits['maxBytes'] + 1)).digest() == hashlib.sha256(image_bytes).digest()

    patch = {'state': 'closed', 'expectedRevision': detail['revision'], 'requestId': str(uuid.uuid4())}
    updated = call('PATCH', path, patch)
    comment = {'content': 'Verified using the documented public HTTP API.', 'expectedRevision': updated['revision'], 'requestId': str(uuid.uuid4())}
    commented = call('POST', path + '/comments', comment)
    assert commented == call('POST', path + '/comments', comment)
    final = call('GET', path)
    comments = call('GET', path + '/comments')
    assert final['state'] == 'closed' and final['revision'] == commented['revision']
    assert comments['total'] == 1
    # 固定同一輪filters／perPage；先處理本批，再保存下一個cursor。
    query = {'perPage': 1}
    cursor = None
    watermark = None
    while True:
        current = call('GET', '/api/public/v1/projects/' + project + '/issue-changes?' + urllib.parse.urlencode({**query, **({'cursor': cursor} if cursor else {})}))
        assert watermark is None or watermark == current['syncUntil']
        watermark = current['syncUntil']
        # 實務上依id／revision upsert，處理成功後才存nextCursor；本範例不累積全集。
        cursor = current['nextCursor']
        if cursor is None:
            break
    # 本輪完成後才能以watermark作下一輪inclusive updatedSince。
    call('GET', '/api/public/v1/projects/' + project + '/issue-changes?' + urllib.parse.urlencode({'updatedSince': watermark, 'perPage': 1}))
    # 小待辦：建一筆自己的待辦（重送不會多一筆）, 從交辦給我的未完成待辦找到它, 再完成。
    todo_request = {'content': 'Python todo example\n\nverify then complete', 'requestId': str(uuid.uuid4())}
    todo = call('POST', '/api/public/v1/todos', todo_request)
    assert todo == call('POST', '/api/public/v1/todos', todo_request), '相同key須回原結果'
    mine = call('GET', '/api/public/v1/todos?' + urllib.parse.urlencode({'scope': 'mine', 'state': 'open', 'perPage': 100}))
    assert [item['id'] for item in mine['items']].count(todo['id']) == 1
    done = call('POST', '/api/public/v1/todos/' + todo['id'] + '/complete', {'expectedRevision': todo['revision'], 'requestId': str(uuid.uuid4())})
    assert call('GET', '/api/public/v1/todos/' + todo['id'])['state'] == 'done' and done['revision'] == todo['revision'] + 1
    print(json.dumps({'result': 'PASS', 'createdOnce': True, 'commentedOnce': True, 'finalRevision': final['revision'], 'closed': True, 'todoCompleted': True, 'attachmentVerified': bool(image)}))


if __name__ == '__main__':
    main()
