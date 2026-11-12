#!/usr/bin/env python3
"""TaskTracker專案本機交接工具；只使用既有Session／CSRF應用API。"""
import argparse
import http.cookiejar
import json
from pathlib import Path
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_CREDENTIALS = ROOT / '.local/tasktracker-codex.json'
MANIFEST = ROOT / 'docs/planning/tasktracker-backlog.json'


class ApiError(RuntimeError):
    def __init__(self, code, message):
        self.code = code
        super().__init__(f'{code}: {message}')


class Client:
    def __init__(self, config):
        self.config = config
        self.base = config['baseUrl'].rstrip('/')
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
        self.csrf = ''
        self.workspace_id = None

    def call(self, path, payload=None):
        if not self.csrf and payload is not None:
            self.csrf = self.call('/api/csrf-cookie')['csrfToken']
        headers = {'Accept': 'application/json', 'Origin': self.base}
        if payload is not None:
            headers.update({'Content-Type': 'application/json', 'X-CSRF-TOKEN': self.csrf})
        if self.workspace_id and path.startswith(('/api/rpc/', '/api/v1/')):
            headers['X-Workspace-ID'] = self.workspace_id
        req = urllib.request.Request(self.base + path, data=None if payload is None else json.dumps(payload).encode(), headers=headers)
        # 同一次mutation重試沿用payload內requestId；不在401／403或驗證失敗時重試。
        for attempt in range(3):
            try:
                with self.opener.open(req, timeout=40) as response:
                    result = json.load(response)
                    self.csrf = response.headers.get('X-CSRF-TOKEN') or self.csrf
                if result.get('ok') is False:
                    error = result.get('error', {})
                    raise ApiError(error.get('code', 'API_ERROR'), error.get('message', '操作失敗'))
                return result.get('data', result)
            except urllib.error.HTTPError as error:
                if error.code in (502, 503, 504) and attempt < 2:
                    time.sleep(2 ** attempt)
                    continue
                try:
                    body = json.load(error).get('error', {})
                except (ValueError, AttributeError):
                    body = {}
                raise ApiError(body.get('code', f'HTTP_{error.code}'), body.get('message', 'API請求失敗')) from None
            except (TimeoutError, urllib.error.URLError):
                if attempt == 2:
                    raise RuntimeError('連線失敗；相同requestId可於下次安全核對／補送') from None
                time.sleep(2 ** attempt)

    def rpc(self, operation, **payload):
        return self.call('/api/rpc/' + operation, payload)

    def login(self):
        identity = self.call('/api/auth/login', {k: self.config[k] for k in ('email', 'password')})
        if identity['user']['id'] != self.config['userId']:
            raise RuntimeError('登入帳號與交接User ID不符')
        spaces = self.call('/api/auth/workspaces', {})['workspaces']
        target = next((w for w in spaces if w['id'] == self.config['workspaceId']), None)
        if not target:
            raise RuntimeError('指定工作區資格不存在；不改用其他Workspace或Owner帳號')
        self.call('/api/auth/switchWorkspace', {'workspaceId': target['id']})
        self.workspace_id = target['id']
        return identity

    def workspace(self):
        data = self.rpc('getWorkspace')
        if data['meta']['workspace']['id'] != self.config['workspaceId']:
            raise RuntimeError('Workspace不符，停止操作')
        return data

    def project_issues(self, data):
        if not any(p['id'] == self.config['projectId'] for p in data['projects']):
            raise RuntimeError('指定專案不可見，停止操作')
        return [i for i in data['issues'] if i['projectId'] == self.config['projectId']]

    def issue_page(self, page=1, search='', identifier=''):
        query = urllib.parse.urlencode({'page': page, 'perPage': 100, 'search': search, 'identifier': identifier})
        return self.call('/api/v1/projects/' + self.config['projectId'] + '/issues?' + query)

    def find_issue(self, identifier, detail=False):
        rows = self.issue_page(identifier=identifier)['items']
        item = next((i for i in rows if i['identifier'] == identifier), None)
        if item is None:
            raise RuntimeError('找不到可讀任務：' + str(identifier))
        return self.call('/api/v1/projects/' + self.config['projectId'] + '/issues/' + item['id']) if detail else item

    def list_issues(self):
        page = 1
        while True:
            result = self.issue_page(page=page)
            yield from result['items']
            if page >= result['lastPage']:
                break
            page += 1

    def logout(self):
        self.call('/api/auth/logout', {})


def key_for(issue):
    return next((line.removeprefix('追蹤鍵：tasktracker:') for line in issue['description'].splitlines() if line.startswith('追蹤鍵：tasktracker:')), None)


def claim_issue(client, issue):
    """先以revision領取open卡，再變更狀態；衝突停止，不搶領他人任務。"""
    actor = client.config['userId']
    if issue.get('assignee') not in ('', None, actor):
        raise RuntimeError('卡片已指派其他帳號；停止領卡，請先協調')
    if issue['state'] == 'inprogress' and issue.get('assignee') == actor:
        return issue
    if issue['state'] != 'open':
        raise RuntimeError('只能領取open待辦；其他狀態請先確認實際進度')
    if issue.get('assignee') != actor:
        issue = client.rpc('updateIssue', id=issue['id'], assignee=actor,
                           expectedRevision=issue['revision'], requestId=str(uuid.uuid4()))
    return client.rpc('updateIssue', id=issue['id'], state='inprogress',
                      expectedRevision=issue['revision'], requestId=str(uuid.uuid4()))


def import_cards(client, manifest):
    existing = {key_for(i): i for i in client.project_issues(client.workspace())}
    for card in manifest['cards']:
        if card['key'] in existing:
            continue  # 不以初始清單蓋掉之後的實際進度／人工修改
        epic = existing.get(card.get('epic'))
        if card.get('epic') and not epic:
            raise RuntimeError('Epic尚未建立：' + card['key'])
        payload = {k: card[k] for k in ('title', 'description', 'state', 'priority', 'type')}
        issue = client.rpc('createIssue', **payload, projectId=client.config['projectId'], assignee='' if card['state'] == 'open' else client.config['userId'], epicId=epic['id'] if epic else '', requestId=str(uuid.uuid5(uuid.NAMESPACE_URL, 'tasktracker-bootstrap-v1:' + card['key'])))
        existing[card['key']] = issue
        print(issue['identifier'], issue['state'], flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--agent', choices=['codex', 'claude'], default='codex', help='執行者；Claude必須指定--agent claude')
    parser.add_argument('command', choices=['list', 'import', 'verify', 'claim', 'status', 'comment'])
    parser.add_argument('identifier', nargs='?')
    parser.add_argument('--state')
    parser.add_argument('--body-file', type=Path)
    args = parser.parse_args()
    if args.command in ('claim', 'status', 'comment') and not args.identifier:
        parser.error('此操作需要卡號')
    credentials = ROOT / f'.local/tasktracker-{args.agent}.json'
    config = json.loads(credentials.read_text())
    if config.get('displayName') != args.agent:
        raise RuntimeError('憑證與指定Agent不符；不回退共用或Owner帳號')
    client = Client(config)
    client.login()
    try:
        manifest = json.loads(MANIFEST.read_text())
        if args.command == 'import':
            import_cards(client, manifest)
        if args.command in ('import', 'verify'):
            issues = client.project_issues(client.workspace())
        elif args.command == 'list':
            issues = client.list_issues()
        else:
            issues = [client.find_issue(args.identifier)]
        if args.command == 'verify':
            by_key = {key_for(i): i for i in issues}
            assert len(by_key) == len(issues), '重複追蹤鍵或無追蹤鍵卡片'
            for card in manifest['cards']:
                issue = by_key[card['key']]
                assert all(issue[k] == card[k] for k in ('title', 'description', 'state', 'priority', 'type')), card['key']
                assert issue['assignee'] == config['userId'], card['key']
                assert issue['epicId'] == (by_key[card['epic']]['id'] if card.get('epic') else ''), card['key']
            print(f'PASS: {len(issues)} cards; fields, status, assignment and Epic links')
        elif args.command in ('claim', 'status', 'comment'):
            issue = next(i for i in issues if i['identifier'] == args.identifier)
            request_id = str(uuid.uuid4())
            if args.command == 'claim' or (args.command == 'status' and args.state == 'inprogress'):
                claim_issue(client, issue)
            elif args.command == 'status':
                if not args.state:
                    parser.error('status需要--state')
                if issue.get('assignee') != config['userId']:
                    raise RuntimeError('只能更新自己承辦的任務狀態；不自動搶領')
                client.rpc('updateIssue', id=issue['id'], state=args.state, expectedRevision=issue['revision'], requestId=request_id)
            else:
                if not args.body_file:
                    parser.error('comment需要--body-file')
                client.rpc('addComment', id=issue['id'], content=args.body_file.read_text(), expectedRevision=issue['revision'], requestId=request_id)
            print('updated', issue['identifier'])
        else:
            for issue in issues:
                print(issue['identifier'], issue['state'], issue['title'])
    finally:
        client.logout()


if __name__ == '__main__':
    main()
