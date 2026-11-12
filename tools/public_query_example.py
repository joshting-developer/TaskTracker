#!/usr/bin/env python3
"""純標準HTTP的公開查詢範例；只用環境PAT、不登入、不讀TaskTracker原始碼服務。"""
import json
import os
import urllib.parse
import urllib.request


def main():
    base = os.environ['BASE_URL'].rstrip('/')
    token = os.environ['TASKTRACKER_TOKEN']
    project = os.environ.get('PROJECT_ID')
    identifier = os.environ['ISSUE_IDENTIFIER']
    def get(path):
        request = urllib.request.Request(base + path, headers={'Authorization': 'Bearer ' + token, 'Accept': 'application/json'})
        with urllib.request.urlopen(request, timeout=15) as response:
            assert not response.headers.get('Set-Cookie'), '公開API不應啟動Session'
            return json.load(response)['data']
    identity = get('/api/public/v1/me')
    projects = get('/api/public/v1/projects?perPage=20')
    if not project:
        assert projects['items'], '目前沒有可見Project'
        project = projects['items'][0]['id']
    prefix = '/api/public/v1/projects/' + urllib.parse.quote(project, safe='')
    metadata = get(prefix)
    states = get(prefix + '/statuses')
    members = get(prefix + '/members?perPage=20')
    page = get(prefix + '/issues?' + urllib.parse.urlencode({'identifier': identifier}))
    assert page['total'] == 1, '卡號不在指定可見專案，或查詢結果不唯一'
    issue = get(prefix + '/issues/' + urllib.parse.quote(page['items'][0]['id'], safe=''))
    comments = get(prefix + '/issues/' + urllib.parse.quote(issue['id'], safe='') + '/comments?perPage=20')
    assert issue['identifier'] == identifier
    assert isinstance(issue['revision'], int)
    assert 'description' in issue and 'relatedIssueIds' in issue
    assert all(set(member) == {'id', 'name'} for member in members['items'])
    # 不輸出PAT、身分、描述／留言或其他使用者資訊。
    print(json.dumps({'result': 'PASS', 'projectRead': bool(metadata['id']), 'projectPageRead': isinstance(projects['items'], list), 'statusCount': len(states['items']), 'identifierMatched': True, 'revision': issue['revision'], 'commentTotal': comments['total'], 'workspaceBound': bool(identity['workspaceId'])}))


if __name__ == '__main__':
    main()
